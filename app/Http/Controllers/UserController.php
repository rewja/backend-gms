<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Exports\UserTemplateExport;
use App\Imports\UserImport;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    // GA: list all users
    public function index()
    {
        return response()->json(User::all());
    }

    // GA: show specific user
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // GA: create user
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => ['required', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
            'role' => 'required|in:user,admin_ga,admin_ga_manager,super_admin,procurement',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'category' => 'nullable|in:ob,driver,security,magang_pkl',
        ]);

        // Only privileged roles can assign elevated roles
        $currentUser = $request->user();
        $elevatedRoles = ['admin_ga', 'admin_ga_manager', 'super_admin'];
        if (in_array($data['role'] ?? 'user', $elevatedRoles, true)) {
            if (!$currentUser || !in_array($currentUser->role, $elevatedRoles, true)) {
                return response()->json(['message' => 'Insufficient permissions to assign elevated role'], 403);
            }
        }

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);


        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    // GA: update user
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => ['nullable', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
            'role' => 'sometimes|in:user,admin_ga,admin_ga_manager,super_admin,procurement',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'category' => 'nullable|in:ob,driver,security,magang_pkl',
        ]);
        
        // Prevent self role changes and restrict role updates to privileged roles
        if (array_key_exists('role', $data)) {
            $currentUser = $request->user();
            $elevatedRoles = ['admin_ga', 'admin_ga_manager', 'super_admin'];
            if (!$currentUser) {
                unset($data['role']);
            } else {
                if ($currentUser->id === $user->id) {
                    return response()->json(['message' => 'Cannot change own role'], 403);
                }
                if (!in_array($currentUser->role, $elevatedRoles, true)) {
                    return response()->json(['message' => 'Insufficient permissions to change role'], 403);
                }
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);


        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    // GA: delete user
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // User: get own profile
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // Admin: new users per month/year
    public function stats()
    {
        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y")' : 'strftime("%Y", created_at)';

        $monthly = \DB::table('users')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('users')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        return response()->json([
            'monthly' => $monthly,
            'yearly' => $yearly,
        ]);
    }

    // GA: download user import template
    public function downloadTemplate()
    {
        return Excel::download(new UserTemplateExport, 'template_import_pengguna.xlsx');
    }

    // GA: import users from Excel
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240', // 10MB max
        ]);

        try {
            $import = new UserImport();
            // Import only the first sheet (Template Import Pengguna), ignore other sheets like "Petunjuk"
            Excel::import($import, $request->file('file'), null, \Maatwebsite\Excel\Excel::XLSX);

            $successCount = $import->getSuccessCount();
            $errors = $import->errors();
            $failures = $import->failures();
            $processedRows = $import->getProcessedRowsCount(); // Only rows with actual data
            
            // Log for debugging
            \Log::info('Import result', [
                'success_count' => $successCount,
                'errors_count' => count($errors),
                'failures_count' => count($failures),
                'processed_rows' => $processedRows,
            ]);

            // Total rows = only rows that have data (not empty rows)
            // Only count rows that were actually processed (have some data)
            $totalRows = max($processedRows, $successCount + count($failures));

            $response = [
                'message' => 'Import selesai',
                'success_count' => $successCount,
                'total_rows' => $totalRows,
            ];

            if (count($errors) > 0) {
                $response['errors'] = $errors;
            }

            if (count($failures) > 0) {
                // Convert to array if it's a collection
                $failuresArray = [];
                if (is_array($failures)) {
                    $failuresArray = $failures;
                } elseif (is_object($failures) && method_exists($failures, 'toArray')) {
                    $failuresArray = $failures->toArray();
                } elseif (is_object($failures) && method_exists($failures, 'all')) {
                    $failuresArray = $failures->all();
                } else {
                    // Try to iterate
                    foreach ($failures as $failure) {
                        $failuresArray[] = $failure;
                    }
                }
                
                $response['failures'] = [];
                foreach ($failuresArray as $index => $failure) {
                    // Handle both object and array formats
                    if (is_object($failure)) {
                        // Check if it has the methods we need - use try-catch for safety
                        try {
                            // Try multiple ways to get row number
                            $row = null;
                            if (method_exists($failure, 'row')) {
                                $row = $failure->row();
                            } elseif (property_exists($failure, 'row')) {
                                $row = $failure->row;
                            } elseif (method_exists($failure, 'getRow')) {
                                $row = $failure->getRow();
                            } elseif (isset($failure->row)) {
                                $row = $failure->row;
                            }
                            
                        // If row is still null, use index + 2 (since WithHeadingRow, first data row is index 0 = Excel row 2)
                        if ($row === null || $row === '?') {
                            $row = $index + 2; // Index 0 = Excel row 2 (1 for header + 1 for first data)
                        }
                        
                        $attribute = method_exists($failure, 'attribute') ? $failure->attribute() : (property_exists($failure, 'attribute') ? $failure->attribute : null);
                        $errors = method_exists($failure, 'errors') ? $failure->errors() : (property_exists($failure, 'errors') ? $failure->errors : ['Data tidak valid']);
                        $values = method_exists($failure, 'values') ? $failure->values() : (property_exists($failure, 'values') ? $failure->values : []);
                    } catch (\Exception $e) {
                        // If calling methods fails, try to get as properties
                        $row = isset($failure->row) ? $failure->row : ($index + 2);
                        $attribute = isset($failure->attribute) ? $failure->attribute : null;
                        $errors = isset($failure->errors) ? $failure->errors : ['Data tidak valid'];
                        $values = isset($failure->values) ? $failure->values : [];
                    }
                        
                        // Convert errors to array if needed
                        if (!is_array($errors)) {
                            $errors = [$errors];
                        }
                        
                        // Log raw errors for debugging
                        \Log::info('Raw errors from failure (success path)', [
                            'errors' => $errors,
                            'errors_type' => gettype($errors),
                            'attribute' => $attribute,
                            'values' => $values,
                        ]);
                        
                        // Check if errors contain specific messages or are generic
                        $hasSpecificError = false;
                        foreach ($errors as $error) {
                            $errorStr = is_string($error) ? strtolower($error) : '';
                            // Check if error message is specific (not generic)
                            if (!empty($errorStr) && 
                                $errorStr !== 'data tidak valid' && 
                                stripos($errorStr, 'data tidak valid') === false &&
                                stripos($errorStr, 'validation') === false &&
                                stripos($errorStr, 'invalid') === false) {
                                $hasSpecificError = true;
                                break;
                            }
                        }
                        
                        // Only use fallback if no specific error message found
                        if (!$hasSpecificError && !empty($values)) {
                            // Try to infer error from values
                            $errorMessages = [];
                            if (isset($values['email']) && User::where('email', $values['email'])->exists()) {
                                $errorMessages[] = "Email {$values['email']} sudah terdaftar di database. Gunakan email lain";
                            }
                            if (isset($values['password']) && strlen($values['password']) < 8) {
                                $errorMessages[] = "Password minimal 8 karakter";
                            }
                            if (isset($values['password']) && !preg_match('/[A-Z]/', $values['password'])) {
                                $errorMessages[] = "Password harus mengandung minimal 1 huruf besar (A-Z)";
                            }
                            if (isset($values['password']) && !preg_match('/[a-z]/', $values['password'])) {
                                $errorMessages[] = "Password harus mengandung minimal 1 huruf kecil (a-z)";
                            }
                            if (isset($values['password']) && !preg_match('/[0-9]/', $values['password'])) {
                                $errorMessages[] = "Password harus mengandung minimal 1 angka (0-9)";
                            }
                            if (!empty($errorMessages)) {
                                $errors = $errorMessages;
                            }
                        }
                        
                        // WithHeadingRow: row() returns 1-based index for data rows (row 2 in Excel = 1)
                        // So we need to add 1 to get the actual Excel row number
                        // But if row is already from index (already Excel row), use as is
                        if (is_numeric($row) && $row > 0) {
                            // If row is from failure object (1-based), add 1. If from index (already Excel row), use as is
                            if ($row == 1) {
                                // From failure object (1-based), add 1 for header
                                $excelRow = $row + 1;
                            } else {
                                // Already Excel row number (from index or > 1)
                                $excelRow = $row;
                            }
                        } else {
                            $excelRow = $row ?? ($index + 2);
                        }
                        
                    // Log for debugging - log the entire failure object
                    \Log::info('Failure details (success path)', [
                        'failure_type' => get_class($failure),
                        'failure_methods' => get_class_methods($failure),
                        'failure_properties' => get_object_vars($failure),
                        'row' => $row,
                        'excel_row' => $excelRow,
                        'attribute' => $attribute,
                        'errors' => $errors,
                        'errors_type' => gettype($errors),
                        'errors_raw' => method_exists($failure, 'errors') ? $failure->errors() : null,
                        'values' => $values,
                        'values_type' => gettype($values),
                    ]);
                        
                        $response['failures'][] = [
                            'row' => $excelRow,
                            'attribute' => $attribute,
                            'errors' => $errors,
                            'values' => $values,
                        ];
                    } else {
                        // Already an array
                        $errors = $failure['errors'] ?? $failure['message'] ?? ['Data tidak valid'];
                        if (!is_array($errors)) {
                            $errors = [$errors];
                        }
                        
                        // Only use fallback if errors is truly generic or empty
                        // Don't override specific error messages from Laravel validation
                        $values = $failure['values'] ?? [];
                        $isGenericError = empty($errors) || 
                            (count($errors) == 1 && (
                                $errors[0] == 'Data tidak valid' || 
                                stripos($errors[0], 'data tidak valid') !== false ||
                                stripos($errors[0], 'validation') !== false
                            ));
                        
                        if ($isGenericError && !empty($values)) {
                            // Try to infer error from values
                            $errorMessages = [];
                            if (isset($values['email']) && User::where('email', $values['email'])->exists()) {
                                $errorMessages[] = "Email {$values['email']} sudah terdaftar di database. Gunakan email lain";
                            }
                            if (isset($values['password']) && strlen($values['password']) < 8) {
                                $errorMessages[] = "Password minimal 8 karakter";
                            }
                            if (isset($values['password']) && !preg_match('/[A-Z]/', $values['password'])) {
                                $errorMessages[] = "Password harus mengandung minimal 1 huruf besar (A-Z)";
                            }
                            if (isset($values['password']) && !preg_match('/[a-z]/', $values['password'])) {
                                $errorMessages[] = "Password harus mengandung minimal 1 huruf kecil (a-z)";
                            }
                            if (isset($values['password']) && !preg_match('/[0-9]/', $values['password'])) {
                                $errorMessages[] = "Password harus mengandung minimal 1 angka (0-9)";
                            }
                            if (!empty($errorMessages)) {
                                $errors = $errorMessages;
                            }
                        }
                        
                        // Get row number from array
                        $rowNum = $failure['row'] ?? null;
                        if ($rowNum === null) {
                            // Try to get from index
                            $rowNum = $index + 2;
                        }
                        
                        // Ensure row number is correct (Excel row, not array index)
                        if (is_numeric($rowNum) && $rowNum > 0) {
                            if ($rowNum == 1) {
                                $excelRow = $rowNum + 1;
                            } else {
                                $excelRow = $rowNum;
                            }
                        } else {
                            $excelRow = $rowNum ?? ($index + 2);
                        }
                        
                        $response['failures'][] = [
                            'row' => $excelRow,
                            'attribute' => $failure['attribute'] ?? null,
                            'errors' => $errors,
                            'values' => $failure['values'] ?? [],
                        ];
                    }
                }
            }

            return response()->json($response, 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $failureData = [];
            
            foreach ($failures as $index => $failure) {
                $errors = [];
                if (is_object($failure)) {
                    try {
                        $errors = method_exists($failure, 'errors') ? $failure->errors() : (property_exists($failure, 'errors') ? $failure->errors : ['Data tidak valid']);
                        if (!is_array($errors)) {
                            $errors = [$errors];
                        }
                    } catch (\Exception $ex) {
                        $errors = ['Data tidak valid'];
                    }
                } else {
                    $errors = $failure['errors'] ?? $failure['message'] ?? ['Data tidak valid'];
                    if (!is_array($errors)) {
                        $errors = [$errors];
                    }
                }
                
                // Get row number
                $rowNum = null;
                if (is_object($failure)) {
                    try {
                        if (method_exists($failure, 'row')) {
                            $rowNum = $failure->row();
                        } elseif (property_exists($failure, 'row')) {
                            $rowNum = $failure->row;
                        }
                    } catch (\Exception $ex) {
                        // Ignore
                    }
                } else {
                    $rowNum = $failure['row'] ?? null;
                }
                
                if ($rowNum === null) {
                    $rowNum = $index + 2;
                }
                
                // Ensure correct Excel row number
                if (is_numeric($rowNum) && $rowNum > 0) {
                    if ($rowNum == 1) {
                        $excelRow = $rowNum + 1;
                    } else {
                        $excelRow = $rowNum;
                    }
                } else {
                    $excelRow = $rowNum ?? ($index + 2);
                }
                
                $failureData[] = [
                    'row' => $excelRow,
                    'attribute' => is_object($failure) ? (method_exists($failure, 'attribute') ? $failure->attribute() : (property_exists($failure, 'attribute') ? $failure->attribute : null)) : ($failure['attribute'] ?? null),
                    'errors' => $errors,
                    'values' => is_object($failure) ? (method_exists($failure, 'values') ? $failure->values() : (property_exists($failure, 'values') ? $failure->values : [])) : ($failure['values'] ?? []),
                ];
            }
            
            return response()->json([
                'message' => 'Validasi gagal. Silakan periksa data di baris yang disebutkan.',
                'success_count' => 0,
                'failures' => $failureData,
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            $errorMessage = 'Gagal mengimport data. Pastikan format file sesuai dengan template.';
            if (config('app.debug')) {
                $errorMessage .= ' Error: ' . $e->getMessage();
            }
            
            return response()->json([
                'message' => $errorMessage,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }
    }
}
