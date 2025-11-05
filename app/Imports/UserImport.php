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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    protected $successCount = 0;

    public function model(array $row)
    {
        // Map Excel columns to user fields
        // Handle both Indonesian and English column names
        $name = $row['nama'] ?? $row['name'] ?? null;
        $email = $row['email'] ?? null;
        $password = $row['password'] ?? null;
        $role = $this->normalizeRole($row['role'] ?? $row['peran'] ?? 'user');
        $category = $this->normalizeCategory($row['kategori'] ?? $row['category'] ?? null);

        // Validate required fields
        if (empty($name) || empty($email) || empty($password)) {
            return null;
        }

        // Generate password if not provided (min 8 chars with mixed case and numbers)
        if (strlen($password) < 8) {
            // Generate a default password
            $password = $this->generateDefaultPassword($email);
        }

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            return null; // Skip duplicate emails, will be caught by validation
        }

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
        return [
            '*.nama' => ['required', 'string', 'max:100'],
            '*.email' => ['required', 'email', 'unique:users,email'],
            '*.password' => ['required', 'string', 'min:8'],
            '*.role' => ['nullable', 'in:user,admin_ga,admin_ga_manager,super_admin,procurement'],
            '*.kategori' => ['nullable', 'in:ob,driver,security,magang_pkl'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nama.required' => 'Nama wajib diisi',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'role.in' => 'Role tidak valid',
            'kategori.in' => 'Kategori tidak valid',
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
}

