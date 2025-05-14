<?php
session_start();
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Check if username or email already exists
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Hash password and insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sss", $username, $email, $hashed_password);

            if ($stmt->execute()) {
                $success = 'Registration successful! You can now login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AI Savings Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            width: 100%;
            padding: 1rem;
            margin: 0 auto;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 900px;
            padding: 0;
            overflow: hidden;
            margin: 0 auto;
        }
        .register-form-container {
            padding: 2rem;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header i {
            font-size: 3rem;
            color: #00b894;
            margin-bottom: 1rem;
        }
        .register-header h1 {
            font-size: 1.8rem;
            color: #2d3436;
            margin-bottom: 0.5rem;
        }
        .register-header p {
            color: #636e72;
            margin-bottom: 0;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.8rem;
            border: 1px solid #dfe6e9;
        }
        .form-control:focus {
            border-color: #00b894;
            box-shadow: 0 0 0 0.2rem rgba(0, 184, 148, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            border: none;
            border-radius: 10px;
            padding: 0.8rem;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #00cec9 0%, #00b894 100%);
        }
        .alert {
            border-radius: 10px;
        }
        
        /* Animated Text Styles */
        .animated-text-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 2rem;
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            color: white;
            min-height: 100%;
        }
        
        .typing-text {
            font-size: 2rem;
            font-weight: 600;
            text-align: left;
            line-height: 1.6;
            position: relative;
        }

        .typing-line {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            width: 0;
            margin-bottom: 0.5em;
            animation: typing 8s steps(20) infinite;
        }

        .typing-line:nth-child(1) {
            animation-delay: 0s;
        }

        .typing-line:nth-child(2) {
            animation-delay: 2s;
        }

        .typing-line:nth-child(3) {
            animation-delay: 4s;
        }

        @keyframes typing {
            0%, 15% { width: 0 }
            30%, 85% { width: 100% }
            100% { width: 0 }
        }

        @media (max-width: 768px) {
            .register-card {
                max-width: 400px;
            }
            
            .animated-text-container {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .typing-text {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center">
            <div class="col-12">
                <div class="card register-card">
                    <div class="row g-0">
                        <div class="col-md-6">
                            <div class="register-form-container">
                                <div class="register-header">
                                    <i class="fas fa-robot"></i>
                                    <h1>AI Savings Assistant</h1>
                                    <p>Join us and start your smart savings journey</p>
                                </div>
                                
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" class="form-control" name="username" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" name="password" required>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label">Confirm Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-user-plus me-2"></i> Register
                                    </button>
                                    
                                    <div class="text-center mt-3">
                                        <a href="login.php" class="text-decoration-none">
                                            Already have an account? Login here
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="animated-text-container">
                                <div class="typing-text">
                                    <span class="typing-line">AI thinks about</span>
                                    <span class="typing-line">your savings</span>
                                    <span class="typing-line">so you don't have to.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 