<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Xử lý đăng ký người dùng mới.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:5', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone_number' => ['nullable', 'string', 'regex:/^0[0-9]{9,10}$/'],
        ], [
            'username.alpha_dash' => 'Tên đăng nhập chỉ có thể chứa chữ cái, số, dấu gạch ngang và dấu gạch dưới.',
            'username.min' => 'Tên đăng nhập phải có ít nhất 5 ký tự.',
            'username.unique' => 'Tên đăng nhập này đã tồn tại.',
            'email.unique' => 'Địa chỉ email này đã tồn tại.',
            'phone_number.regex' => 'Số điện thoại không hợp lệ.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.mixedCase' => 'Mật khẩu phải chứa cả chữ hoa và chữ thường.',
            'password.numbers' => 'Mật khẩu phải chứa ít nhất một số.',
            'password.symbols' => 'Mật khẩu phải chứa ít nhất một ký tự đặc biệt.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'role_id' => 3, // Mặc định role_id = 3 (customer)
        ]);

        return response()->json([
            'message' => 'Đăng ký thành công!',
            'user' => $user,
        ], 201);
    }


    /**
     * Xử lý đăng nhập người dùng.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('username', 'password'))) {
            throw ValidationException::withMessages([
                'username' => ['Thông tin đăng nhập không hợp lệ.'],
            ]);
        }

        $user = Auth::user();

        // Tạo token cho user.
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công!',
            'user' => $user->load('role'),
            'token' => $token,
        ]);
    }

    /**
     * Đăng xuất người dùng.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công!']);
    }

    /**
     * Trả về thông tin người dùng đã xác thực.
     */
    public function user(Request $request)
    {
        return $request->user()->load('role');
    }
}
