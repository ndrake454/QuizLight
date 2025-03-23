<?php
require_once 'config.php';
$pageTitle = 'Register';

// If user is already logged in, redirect to home page
if (isLoggedIn()) {
    header("Location: /");
    exit;
}

// Handle registration form submission
$error = '';
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email is already registered. Please use a different email or login.';
            } else {
                // Generate verification code
                $verificationCode = bin2hex(random_bytes(16));
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user into database
                $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, verification_code, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                
                if (!$stmt->execute([$email, $hashedPassword, $firstName, $lastName, $verificationCode])) {
                    throw new Exception("Failed to insert user into database");
                }
                
                // Get the user ID
                $userId = $pdo->lastInsertId();
                
                // Generate verification link
                $verificationLink = $site_url . "/verify.php?code=" . $verificationCode;
                
                // Prepare email content
                $subject = "Verify your account at " . $site_name;
                $message = "
                <html>
                <head>
                    <title>Verify Your Account</title>
                </head>
                <body>
                    <p>Hello $firstName,</p>
                    <p>Thank you for registering at $site_name. Please click the link below to verify your account:</p>
                    <p><a href='$verificationLink'>Verify My Account</a></p>
                    <p>Or copy and paste this URL into your browser:</p>
                    <p>$verificationLink</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>Best regards,<br>$site_name Team</p>
                </body>
                </html>
                ";
                
                // Set email headers
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: $from_email" . "\r\n";
                
                // Try to send email
                $mailSent = mail($email, $subject, $message, $headers);
                
                $success = 'Registration successful! ';
                if ($mailSent) {
                    $success .= 'Please check your email to verify your account.';
                } else {
                    $success .= 'Please contact admin for account verification.';
                }
            }
        } catch (Exception $e) {
            $error = 'Server error. Please try again later.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-lg mx-auto my-10">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Create an Account</h2>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($success); ?></p>
                <p class="mt-2">You can now <a href="login.php" class="font-bold underline">login</a> after verifying your email.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="" x-data="{
                first_name: '', 
                last_name: '', 
                email: '', 
                password: '', 
                confirm_password: '',
                showPassword: false,
                showConfirmPassword: false,
                passwordStrength: 0,
                
                checkPasswordStrength() {
                    let strength = 0;
                    const password = this.password;
                    
                    if (password.length >= 8) strength += 1;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
                    if (password.match(/\d/)) strength += 1;
                    if (password.match(/[^a-zA-Z\d]/)) strength += 1;
                    
                    this.passwordStrength = strength;
                }
            }">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="first_name" class="block text-gray-700 text-sm font-bold mb-2">First Name</label>
                        <input type="text" id="first_name" name="first_name" x-model="first_name" required 
                               class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               placeholder="Enter your first name">
                    </div>
                    <div>
                        <label for="last_name" class="block text-gray-700 text-sm font-bold mb-2">Last Name</label>
                        <input type="text" id="last_name" name="last_name" x-model="last_name" required 
                               class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               placeholder="Enter your last name">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                    <input type="email" id="email" name="email" x-model="email" required 
                           class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your email">
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" 
                               x-model="password" @input="checkPasswordStrength()" required
                               class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               placeholder="Enter your password">
                        <button type="button" @click="showPassword = !showPassword" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="!showPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="showPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Password strength indicator -->
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="h-2.5 rounded-full transition-all duration-300"
                                 :class="{
                                     'w-1/4 bg-red-500': passwordStrength === 1,
                                     'w-2/4 bg-yellow-500': passwordStrength === 2,
                                     'w-3/4 bg-blue-500': passwordStrength === 3,
                                     'w-full bg-green-500': passwordStrength === 4,
                                     'w-0': passwordStrength === 0
                                 }">
                            </div>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">Password should be at least 8 characters with uppercase, lowercase, numbers, and special characters.</p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                    <div class="relative">
                        <input :type="showConfirmPassword ? 'text' : 'password'" id="confirm_password" name="confirm_password" 
                               x-model="confirm_password" required
                               class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               :class="{'border-red-500': confirm_password && password !== confirm_password}"
                               placeholder="Confirm your password">
                        <button type="button" @click="showConfirmPassword = !showConfirmPassword" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="!showConfirmPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="showConfirmPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="confirm_password && password !== confirm_password" class="text-red-500 text-xs mt-1">
                        Passwords do not match
                    </div>
                </div>
                
                <div class="mb-6">
                    <button type="submit" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                        Create Account
                    </button>
                </div>
                
                <div class="text-center">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="login.php" class="text-indigo-600 hover:text-indigo-800 transition duration-150">
                            Login here
                        </a>
                    </p>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>