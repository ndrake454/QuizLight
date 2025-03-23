<!-- app/views/auth/login.php -->
<div class="max-w-md mx-auto my-10">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Login to Your Account</h2>
        
        <form method="POST" action="/login/process">
            <div class="mb-6">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" required value="<?php echo $this->old('email'); ?>"
                       class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" required
                       class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            
            <div class="mb-6">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                    Login
                </button>
            </div>
            
            <div class="text-center">
                <p class="text-gray-600">
                    <a href="/forgot-password" class="text-indigo-600 hover:text-indigo-800 transition duration-150">
                        Forgot Password?
                    </a>
                </p>
                
                <p class="text-gray-600 mt-4">
                    Don't have an account? 
                    <a href="/register" class="text-indigo-600 hover:text-indigo-800 transition duration-150">
                        Register here
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>