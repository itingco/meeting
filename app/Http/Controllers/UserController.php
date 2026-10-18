<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
class UserController extends Controller
{
    public function index() { return view('users.index', ['users'=>User::orderBy('name')->get()]); }
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'email' => ['required','email','max:190','unique:users,email'],
            'password' => ['required','confirmed',Password::min(10)],
        ]);
        User::create([
            ...$data,
            'is_admin' => $request->boolean('is_admin'),
            'can_view_history' => $request->boolean('can_view_history'),
            'can_manage_settings' => $request->boolean('can_manage_settings'),
            'is_active' => true,
        ]);
        return back()->with('message', 'User berhasil ditambahkan.');
    }
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'=>['required','string','max:150'],
            'email'=>['required','email','max:190',Rule::unique('users','email')->ignore($user->id)],
            'password'=>['nullable','confirmed',Password::min(10)],
        ]);
        if ($user->is($request->user()) && (!$request->boolean('is_admin') || !$request->boolean('is_active'))) {
            return back()->with('error', 'Tidak dapat menonaktifkan atau melepas hak admin milik akun sendiri.');
        }
        $updates = [
            'name'=>$data['name'], 'email'=>$data['email'],
            'is_admin'=>$request->boolean('is_admin'),
            'can_view_history'=>$request->boolean('can_view_history'),
            'can_manage_settings'=>$request->boolean('can_manage_settings'),
            'is_active'=>$request->boolean('is_active'),
        ];
        if (!empty($data['password'])) $updates['password'] = $data['password'];
        $user->update($updates);
        return back()->with('message', 'Hak akses user berhasil diperbarui.');
    }
    public function passwordForm() { return view('users.password'); }
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password'=>['required','current_password'],
            'password'=>['required','confirmed',Password::min(10)],
        ]);
        $request->user()->update(['password'=>$data['password']]);
        // Logout sesi lain; sesi sekarang tetap aktif.
        return back()->with('message', 'Password berhasil diubah.');
    }
}
