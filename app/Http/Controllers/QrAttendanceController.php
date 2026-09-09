<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\QrToken;
use App\Models\StaffAttendanceQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrAttendanceController extends Controller
{
    public function scan(Request $request)
    {
        $token = QrToken::where('token', $request->query('token'))
            ->where('is_used', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        return view('public.qr-attendance-scan', ['token' => $token->token]);
    }

    public function process(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'employee_id' => ['required', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $token = QrToken::where('token', $data['token'])
                ->where('is_used', false)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();

            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Token invalide ou expiré.'], 422);
            }

            $identifier = trim($data['employee_id']);
            $employee = Employee::where('entreprise_id', $token->entreprise_id)
                ->where('status', 'active')
                ->where(function ($query) use ($identifier) {
                    $query->where('matricule', $identifier)
                        ->orWhere('phone', $identifier)
                        ->orWhere('email', $identifier)
                        ->orWhere('id', ctype_digit($identifier) ? (int) $identifier : 0);
                })->first();

            if (!$employee) {
                return response()->json(['success' => false, 'message' => 'Employé non trouvé. Vérifiez votre identifiant.'], 422);
            }

            $today = now()->toDateString();
            $attendance = StaffAttendanceQr::where('entreprise_id', $token->entreprise_id)
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $today)
                ->lockForUpdate()
                ->first();

            $photoPath = $request->hasFile('photo')
                ? $request->file('photo')->store('attendance_photos/' . $employee->id, 'public')
                : null;

            if (!$attendance) {
                StaffAttendanceQr::create([
                    'entreprise_id' => $token->entreprise_id,
                    'employee_id' => $employee->id,
                    'attendance_date' => $today,
                    'arrival_time' => now()->format('H:i:s'),
                    'scan_date' => now(),
                    'status' => 'arrival',
                    'photo_path' => $photoPath,
                    'verification_status' => 'verified',
                    'verification_details' => 'Pointage QR validé',
                    'verified_at' => now(),
                ]);
                $message = 'Arrivée enregistrée avec succès !';
                $event = 'arrival';
            } elseif (!$attendance->departure_time) {
                $attendance->update([
                    'departure_time' => now()->format('H:i:s'),
                    'status' => 'complete',
                    'photo_path' => $photoPath ?: $attendance->photo_path,
                ]);
                $message = 'Départ enregistré avec succès !';
                $event = 'departure';
            } else {
                return response()->json(['success' => false, 'message' => 'Vous avez déjà terminé votre journée.'], 422);
            }

            $token->update(['employee_id' => $employee->id]);

            return response()->json(['success' => true, 'message' => $message, 'event_type' => $event]);
        });
    }
}
