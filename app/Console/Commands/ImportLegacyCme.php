<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportLegacyCme extends Command
{
    protected $signature = 'legacy:import-cme {--source=diagoma_legacy_cme_import}';
    protected $description = 'Importe les données ERP de l’ancien projet dans CME EXPERTISES.';

    public function handle(): int
    {
        $source = $this->option('source');
        config(['database.connections.legacy' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => $source,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ]]);

        $legacy = DB::connection('legacy');
        $legacy->getPdo();
        $target = DB::connection();

        $target->transaction(function () use ($legacy, $target) {
            $now = now();
            $companyId = $target->table('entreprises')->where('slug', 'cme-expertises')->value('id');
            if (! $companyId) {
                $companyId = $target->table('entreprises')->insertGetId([
                    'name' => 'CME EXPERTISES',
                    'slug' => 'cme-expertises',
                    'database_name' => 'cme_expertises',
                    'is_active' => true,
                    'settings' => json_encode(['locale' => 'fr']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $roles = $target->table('roles')->pluck('id', 'name');
            $departmentNames = $legacy->table('department')->where('entreprise_id', 1)->pluck('department_name', 'id');
            $departmentLabels = [];
            foreach ($departmentNames as $oldId => $name) {
                $label = $this->cleanText($name) ?: 'Département ' . $oldId;
                $target->table('departments')->where('entreprise_id', $companyId)->where('name', $label)->first()
                    ?: $target->table('departments')->insertGetId([
                        'entreprise_id' => $companyId, 'name' => $label, 'description' => 'Import ancien projet',
                        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                $departmentLabels[$oldId] = $label;
            }

            $userIds = [];
            $staffRows = $legacy->table('staff')->where('entreprise_id', 1)->orderBy('id')->get();
            foreach ($staffRows as $staff) {
                $email = $this->email($staff->email, $staff->id);
                $loginEmail = $this->availableEmail($target, $email, $companyId);
                $roleName = $staff->id === 1 ? 'admin' : 'standard_user';
                $userId = $target->table('users')->where('email', $loginEmail)->value('id');
                if (! $userId) {
                    $userId = $target->table('users')->insertGetId([
                        'name' => trim($this->cleanText($staff->name) . ' ' . $this->cleanText($staff->surname)),
                        'email' => $loginEmail,
                        'password' => str_starts_with((string) $staff->password, '$2y$') ? $staff->password : Hash::make('ChangeMe123!'),
                        'entreprise_id' => $companyId,
                        'role_id' => $roles[$roleName] ?? $roles['standard_user'] ?? null,
                        'is_active' => (int) $staff->is_active === 1,
                        'created_at' => $this->date($staff->created_at) ?? $now,
                        'updated_at' => $now,
                    ]);
                }
                $userIds[$staff->id] = $userId;

                $fullName = trim($this->cleanText($staff->name) . ' ' . $this->cleanText($staff->surname));
                $employeeEmail = $this->email($staff->email, $staff->id);
                $employee = $target->table('employees')->where('entreprise_id', $companyId)->where('matricule', $staff->employee_id)->first();
                $employeeData = [
                    'entreprise_id' => $companyId, 'user_id' => $userId, 'matricule' => $staff->employee_id,
                    'full_name' => $fullName ?: 'Employé importé ' . $staff->id, 'first_name' => $this->cleanText($staff->name),
                    'email' => $this->availableEmployeeEmail($target, $employeeEmail, $employee?->id),
                    'phone' => $this->cleanText($staff->contact_no), 'position' => $this->cleanText($staff->designation) ?: 'Employé',
                    'department' => $departmentLabels[$staff->department] ?? ($this->cleanText($staff->department) ?: 'Non renseigné'),
                    'status' => (int) $staff->is_active === 1 ? 'active' : 'inactive',
                    'monthly_salary' => (float) ($staff->basic_salary ?: $staff->salaire_base ?: 0),
                    'hire_date' => $this->date($staff->date_of_joining) ?? $now->toDateString(),
                    'birth_date' => $this->date($staff->dob), 'contract_type' => $this->cleanText($staff->contract_type),
                    'gender' => $this->cleanText($staff->gender), 'nationality' => $this->cleanText($staff->nationalite),
                    'created_at' => $this->date($staff->created_at) ?? $now, 'updated_at' => $now,
                ];
                if ($employee) {
                    $target->table('employees')->where('id', $employee->id)->update($employeeData);
                } else {
                    $target->table('employees')->insert($employeeData);
                }
            }

            $clientIds = [];
            foreach ($legacy->table('clients')->where('entreprise_id', 1)->get() as $client) {
                $name = $this->cleanText($client->item_supplier) ?: trim($this->cleanText($client->lastname)) ?: 'Client importé ' . $client->id;
                $existing = $target->table('commercial_clients')->where('entreprise_id', $companyId)->where('name', $name)->first();
                $data = [
                    'entreprise_id' => $companyId, 'name' => $name, 'responsible_name' => $this->cleanText($client->contact_person_name),
                    'phone' => $this->cleanText($client->phone), 'email' => $this->cleanText($client->email),
                    'city' => $this->cleanText($client->ville), 'tax_id' => $this->cleanText($client->nif ?: $client->ncc),
                    'address' => $this->cleanText($client->address), 'created_at' => $this->date($client->created_at) ?? $now, 'updated_at' => $now,
                ];
                $clientIds[$client->id] = $existing?->id ?: $target->table('commercial_clients')->insertGetId($data);
                if ($existing) {
                    $target->table('commercial_clients')->where('id', $existing->id)->update($data);
                }
            }

            foreach ($legacy->table('banks')->where('entreprise_id', 1)->get() as $bank) {
                $target->table('bank_accounts')->updateOrInsert(
                    ['entreprise_id' => $companyId, 'account_number' => $this->cleanText($bank->account_number) ?: 'LEGACY-' . $bank->id],
                    ['name' => $this->cleanText($bank->name) ?: 'Banque importée', 'bank_name' => $this->cleanText($bank->name) ?: 'Banque',
                     'short_name' => $this->cleanText($bank->code), 'current_balance' => (float) ($bank->balance ?: 0),
                     'status' => (int) $bank->status === 1 ? 'credit' : 'debit',
                     'created_at' => $this->date($bank->created_at) ?? $now, 'updated_at' => $now]
                );
            }

            foreach ($legacy->table('invoices')->where('entreprise_id', 1)->get() as $invoice) {
                $clientName = $clientIds[$invoice->customer_id] ?? null;
                if ($clientName) {
                    $clientName = $target->table('commercial_clients')->where('id', $clientName)->value('name');
                }
                $total = (float) ($invoice->total_ttc ?: $invoice->total_ht ?: 0);
                $paid = (float) ($invoice->amount_paid ?: max(0, $total - (float) $invoice->remaining_amount));
                $status = ((int) $invoice->status === 2 || $paid >= $total) ? 'paid' : (((int) $invoice->status === 5) ? 'cancelled' : ($paid > 0 ? 'partially_paid' : 'unpaid'));
                $reference = $this->cleanText($invoice->invoice_number) ?: 'LEGACY-' . $invoice->id;
                if (! $target->table('custom_invoices')->where('reference', $reference)->exists()) {
                    $target->table('custom_invoices')->insert([
                        'entreprise_id' => $companyId, 'created_by_user_id' => $userIds[$invoice->created_by] ?? null,
                        'reference' => $reference, 'client_name' => $clientName ?: 'Client importé',
                        'quote_date' => $this->date($invoice->invoice_date), 'valid_until' => $this->date($invoice->due_date),
                        'payment_method' => $this->cleanText($invoice->method), 'subject' => 'Facture importée de l’ancien projet',
                        'items' => json_encode([]), 'total_ht' => (float) $invoice->total_ht, 'total_ttc' => $total,
                        'paid_amount' => $paid, 'paid_at' => $this->date($invoice->paid_at), 'status' => $status,
                        'created_at' => $this->date($invoice->created_at) ?? $now, 'updated_at' => $now,
                    ]);
                }
            }

            $this->output->writeln('<info>Entreprise CME EXPERTISES et données principales importées.</info>');
        });

        return self::SUCCESS;
    }

    private function cleanText($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' || $value === '0' || str_starts_with($value, '0000-00-00') ? null : $value;
    }

    private function date($value): ?string
    {
        $value = $this->cleanText($value);
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function email($value, int $id): string
    {
        $value = strtolower(trim((string) $value));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : 'legacy-staff-' . $id . '@cme-expertises.local';
    }

    private function availableEmail($db, string $email, int $companyId): string
    {
        if (! $db->table('users')->where('email', $email)->exists()) {
            return $email;
        }
        return Str::before($email, '@') . '+cme@' . Str::after($email, '@');
    }

    private function availableEmployeeEmail($db, string $email, ?int $currentId): string
    {
        $query = $db->table('employees')->where('email', $email);
        if ($currentId) {
            $query->where('id', '!=', $currentId);
        }
        return $query->exists() ? Str::before($email, '@') . '+cme@' . Str::after($email, '@') : $email;
    }
}
