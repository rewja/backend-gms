<?php

namespace App\Imports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use SkipsErrors, SkipsFailures;

    protected $successCount = 0;
    protected $processedRows = 0; // Count only rows with actual data
    
    public function batchSize(): int
    {
        return 100;
    }
    
    public function chunkSize(): int
    {
        return 100;
    }

    public function model(array $row)
    {
        // Map Excel columns to user fields
        // Handle both Indonesian and English column names (case-insensitive)
        $rowLower = array_change_key_case($row, CASE_LOWER);
        
        $name = trim($rowLower['nama'] ?? $rowLower['name'] ?? '');
        $email = trim($rowLower['email'] ?? '');
        $password = trim($rowLower['password'] ?? '');
        $role = $this->normalizeRole($rowLower['role'] ?? $rowLower['peran'] ?? 'user');
        $category = $this->normalizeCategory($rowLower['kategori'] ?? $rowLower['category'] ?? null);

        // Skip completely empty rows (all required fields are empty or whitespace)
        // This prevents empty rows from being counted at all
        if (empty($name) && empty($email) && empty($password)) {
            return null; // Skip this row completely, won't be processed or counted
        }
        
        // Count this as a processed row (has some data)
        $this->processedRows++;

        // Let validation catch missing fields - don't return null here
        // Validation will provide specific error messages for each missing field
        // If validation passes, then we can create the user

        // Generate password if not provided (min 8 chars with mixed case and numbers)
        if (strlen($password) < 8) {
            // Generate a default password
            $password = $this->generateDefaultPassword($email);
        }

        // Don't check email existence here - let validation handle it
        // This ensures we get proper error messages with row numbers

        $this->successCount++;

        return new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'category' => $category,
        ]);
    }

    public function rules(): array
    {
        // WithHeadingRow converts headers to lowercase and replaces spaces with underscores
        // So "Nama" becomes "nama", "Email" becomes "email", etc.
        // Use 'sometimes' to skip validation for completely empty rows
        return [
            '*.nama' => ['sometimes', 'required', 'string', 'max:100'],
            '*.email' => [
                'sometimes',
                'required', 
                'email', 
                Rule::unique('users', 'email')
            ],
            '*.password' => [
                'sometimes',
                'required', 
                'string', 
                'min:8',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return; // Let required rule handle empty values
                    }
                    // Validate password: must have uppercase, lowercase, and numbers
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('Password harus mengandung minimal 1 huruf besar (A-Z)');
                    }
                    if (!preg_match('/[a-z]/', $value)) {
                        $fail('Password harus mengandung minimal 1 huruf kecil (a-z)');
                    }
                    if (!preg_match('/[0-9]/', $value)) {
                        $fail('Password harus mengandung minimal 1 angka (0-9)');
                    }
                },
            ],
            '*.role' => ['nullable', 'in:user,admin_ga,admin_ga_manager,super_admin,procurement'],
            '*.kategori' => ['nullable', 'in:ob,driver,security,magang_pkl'],
            '*.category' => ['sometimes', 'nullable', 'in:ob,driver,security,magang_pkl'], // Support English too
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.nama.required' => 'Kolom Nama wajib diisi',
            '*.nama.string' => 'Kolom Nama harus berupa teks',
            '*.nama.max' => 'Kolom Nama maksimal 100 karakter',
            '*.email.required' => 'Kolom Email wajib diisi',
            '*.email.email' => 'Kolom Email harus berformat email yang valid (contoh: user@example.com)',
            '*.email.unique' => 'Email :input sudah terdaftar di database. Gunakan email lain',
            '*.password.required' => 'Kolom Password wajib diisi',
            '*.password.min' => 'Kolom Password minimal 8 karakter',
            '*.password.regex' => 'Kolom Password harus mengandung huruf besar, huruf kecil, dan angka',
            '*.role.in' => 'Kolom Role tidak valid. Pilihan yang tersedia: user, admin_ga, admin_ga_manager, super_admin, procurement',
            '*.kategori.in' => 'Kolom Kategori tidak valid. Pilihan yang tersedia: ob, driver, security, magang_pkl',
        ];
    }

    protected function normalizeRole($role)
    {
        if (empty($role)) {
            return 'user';
        }

        $role = strtolower(trim($role));
        
        // Map various role names to standard roles
        $roleMap = [
            'user' => 'user',
            'employee' => 'user',
            'karyawan' => 'user',
            'admin_ga' => 'admin_ga',
            'admin ga' => 'admin_ga',
            'adminga' => 'admin_ga',
            'admin_ga_manager' => 'admin_ga_manager',
            'ga manager' => 'admin_ga_manager',
            'gamanger' => 'admin_ga_manager',
            'super_admin' => 'super_admin',
            'super admin' => 'super_admin',
            'superadmin' => 'super_admin',
            'procurement' => 'procurement',
        ];

        return $roleMap[$role] ?? 'user';
    }

    protected function normalizeCategory($category)
    {
        if (empty($category)) {
            return null;
        }

        $category = strtolower(trim($category));
        
        // Map various category names to standard categories
        $categoryMap = [
            'ob' => 'ob',
            'office boy' => 'ob',
            'driver' => 'driver',
            'supir' => 'driver',
            'security' => 'security',
            'satpam' => 'security',
            'magang_pkl' => 'magang_pkl',
            'magang' => 'magang_pkl',
            'pkl' => 'magang_pkl',
            'magang/pkl' => 'magang_pkl',
        ];

        return $categoryMap[$category] ?? null;
    }

    protected function generateDefaultPassword($email)
    {
        // Generate password from email: first 4 chars of email + "1234" + random chars
        $emailPrefix = substr($email, 0, 4);
        $random = strtoupper(substr(md5(rand()), 0, 4));
        return $emailPrefix . $random . '1234';
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }
    
    public function getProcessedRowsCount()
    {
        return $this->processedRows;
    }
}

