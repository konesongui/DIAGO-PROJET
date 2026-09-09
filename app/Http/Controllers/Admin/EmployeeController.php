<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\SalaryCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class EmployeeController extends AdminController
{
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return response()->json([]);
        }

        $employees = Employee::where('entreprise_id', auth()->user()->entreprise_id)
            ->where(function ($query) use ($term) {
                $query->where('full_name', 'like', '%' . $term . '%')
                    ->orWhere('matricule', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            })
            ->orderBy('full_name')
            ->limit(8)
            ->get(['id', 'full_name', 'matricule', 'position', 'email']);

        return response()->json($employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'matricule' => $employee->matricule,
            'position' => $employee->position,
            'email' => $employee->email,
            'url' => route('admin.rh.employees.show', $employee),
        ])->values());
    }

    public function create()
    {
        return $this->page('rh-employee-form', [
            'title' => 'Nouvel employé',
            'employee' => null,
            'roles' => Role::orderBy('label')->get(),
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'salaryCategories' => SalaryCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return $this->page('rh-employee-form', [
            'title' => 'Modifier un employé',
            'employee' => $employee,
            'roles' => Role::orderBy('label')->get(),
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'salaryCategories' => SalaryCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Employee $employee)
    {
        abort_unless($employee->entreprise_id === auth()->user()->entreprise_id, 403);
        return $this->page('rh-employee-show', [
            'title' => $employee->full_name,
            'employee' => $employee,
            'payrolls' => $employee->payrolls()->latest('year')->latest('month')->get(),
        ]);
    }

    public function destroy(Employee $employee)
    {
        abort_unless($employee->entreprise_id === auth()->user()->entreprise_id, 403);
        $employee->user?->delete();
        $employee->delete();
        return back()->with('success', 'Employé supprimé.');
    }

    public function terminate(Employee $employee)
    {
        abort_unless($employee->entreprise_id === auth()->user()->entreprise_id, 403);
        $employee->update(['status' => 'inactive', 'contract_end_date' => now()->toDateString()]);
        $employee->user?->update(['is_active' => false]);
        return back()->with('success', 'Le contrat de l’employé a été clôturé.');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $entrepriseId = auth()->user()->entreprise_id;
        $plainPassword = Str::random(12);
        $fullName = trim($data['last_name'] . ' ' . ($data['first_name'] ?? ''));
        $matricule = $this->generateMatricule($entrepriseId);

        $user = User::create([
            'name' => $fullName,
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'role_id' => $data['role_id'],
            'entreprise_id' => $entrepriseId,
            'is_active' => true,
        ]);

        $employee = $this->saveEmployee($data, $user->id, $entrepriseId, $request, $matricule);

        Mail::raw(
            "Bonjour {$fullName},\n\nVotre compte Diagoma ERP a été créé.\nMatricule : {$matricule}\nProfil : {$user->role?->label}\nEmail : {$user->email}\nMot de passe temporaire : {$plainPassword}\n\nVeuillez vous connecter puis modifier votre mot de passe.\n\nCordialement.",
            function ($message) use ($user) {
                $message->to($user->email, $user->name)->subject('Vos accès Diagoma ERP');
            }
        );

        return redirect()->route('admin.rh.module', 'personnel')
            ->with('success', "L'employé {$employee->full_name} a été enregistré. Les accès ont été envoyés par email.");
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        $employeeData = collect($data)->except(['role_id', 'last_name'])->all();

        if ($request->hasFile('photo')) {
            $employeeData['photo_path'] = $request->file('photo')->store('employee-photos', 'public');
        }
        if ($request->hasFile('documents')) {
            $newDocuments = collect($request->file('documents'))->map(fn ($document) => [
                'name' => $document->getClientOriginalName(),
                'path' => $document->store('employee-documents', 'public'),
            ]);
            $employeeData['documents'] = collect($employee->documents ?? [])
                ->concat($newDocuments)
                ->take(3)
                ->values()
                ->all();
        }

        $employeeData['full_name'] = trim($data['last_name'] . ' ' . ($data['first_name'] ?? ''));
        $employeeData['hire_date'] = $data['registered_at'];
        $employee->update($employeeData);
        $employee->user?->update([
            'name' => $employeeData['full_name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
        ]);

        return redirect()->route('admin.rh.module', 'personnel')->with('success', 'Employé modifié avec succès.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'civility' => ['nullable', 'in:M.,Mme,Mlle'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'position' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'salary_category' => ['nullable', 'string', 'max:255'],
            'cnps_number' => ['nullable', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:Masculin,Femme'],
            'contract_type' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'registered_at' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'paternity_leave' => ['nullable', 'string', 'max:100'],
            'maternity_leave' => ['nullable', 'string', 'max:100'],
            'annual_leave' => ['nullable', 'string', 'max:100'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'sursalary' => ['nullable', 'numeric', 'min:0'],
            'seniority_bonus' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'responsibility_bonus' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'performance_bonus' => ['nullable', 'numeric', 'min:0'],
            'risk_bonus' => ['nullable', 'numeric', 'min:0'],
            'attendance_bonus' => ['nullable', 'numeric', 'min:0'],
            'gratification' => ['nullable', 'numeric', 'min:0'],
            'leave_pay' => ['nullable', 'numeric', 'min:0'],
            'income_tax' => ['nullable', 'numeric', 'min:0'],
            'cmu' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'indemnities' => ['nullable', 'numeric', 'min:0'],
            'part_igr' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'payment_mode' => ['nullable', 'string', 'max:50'],
            'account_title' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'ifsc_code' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url'],
            'twitter_url' => ['nullable', 'url'],
            'linkedin_url' => ['nullable', 'url'],
            'instagram_url' => ['nullable', 'url'],
            'documents' => ['nullable', 'array', 'max:3'],
            'documents.*' => ['file', 'max:5120'],
        ]);
    }

    private function saveEmployee(array $data, int $userId, int $entrepriseId, Request $request, string $matricule): Employee
    {
        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('employee-photos', 'public')
            : null;
        $documents = [];
        foreach ($request->file('documents', []) as $document) {
            $documents[] = [
                'name' => $document->getClientOriginalName(),
                'path' => $document->store('employee-documents', 'public'),
            ];
        }

        $employeeData = collect($data)->except(['role_id', 'last_name'])->all();

        return Employee::create(array_merge($employeeData, [
            'entreprise_id' => $entrepriseId,
            'user_id' => $userId,
            'matricule' => $matricule,
            'full_name' => trim($data['last_name'] . ' ' . ($data['first_name'] ?? '')),
            'hire_date' => $data['registered_at'],
            'photo_path' => $photoPath,
            'documents' => $documents,
            'status' => 'active',
        ]));
    }

    private function generateMatricule(int $entrepriseId): string
    {
        $lastNumber = Employee::withoutGlobalScopes()
            ->pluck('matricule')
            ->map(function (?string $matricule): int {
                if (preg_match('/^EMP-\d+$/', (string) $matricule) !== 1) {
                    return 0;
                }

                return (int) substr((string) $matricule, 4);
            })
            ->max();

        $number = max(1, $lastNumber + 1);
        for ($attempt = 0; $attempt < 10; $attempt++, $number++) {
            $matricule = 'EMP-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);

            if (! Employee::withoutGlobalScopes()->where('matricule', $matricule)->exists()) {
                return $matricule;
            }
        }

        throw new RuntimeException('Impossible de générer un matricule employé unique.');
    }
}
