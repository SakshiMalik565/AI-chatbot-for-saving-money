<?php
session_start();
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $query = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AI Savings Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .login-form-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 3rem;
            color: #00b894;
            margin-bottom: 1rem;
        }
        .login-header h1 {
            font-size: 1.8rem;
            color: #2d3436;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #636e72;
            margin-bottom: 0;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 2rem;
            border: none;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: none;
        }
        
        .form-label {
            color: #2d3436;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .input-group {
            margin-top: 0.5rem;
        }
        
        .input-group-text {
            background: transparent;
            border-right: none;
            color: #00b894;
        }
        
        .form-control {
            border-left: none;
            border-radius: 10px;
            padding: 0.8rem;
            border: 1px solid #dfe6e9;
        }
        
        .form-control:focus {
            border-color: #00b894;
            box-shadow: 0 0 0 0.2rem rgba(0, 184, 148, 0.25);
            border-left: none;
        }
        
        .input-group > .form-control:focus {
            border-left: none;
        }
        
        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 1px solid #dfe6e9;
        }
        
        .input-group > .form-control {
            border-radius: 0 10px 10px 0;
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
        
        /* Animated Text Styles */
        .animated-text-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 2rem;
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            border-radius: 0 20px 20px 0;
            color: white;
        }
        
        .typing-text {
            font-size: 2.2rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.4;
            position: relative;
        }

        .typing-line {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            width: 0;
            margin-bottom: 0.2em;
        }

        .typing-line:nth-child(1) {
            animation: typing 8s steps(20) infinite;
        }

        .typing-line:nth-child(2) {
            animation: typing 8s steps(20) infinite;
            animation-delay: 2s;
        }

        .typing-line:nth-child(3) {
            animation: typing 8s steps(20) infinite;
            animation-delay: 4s;
        }
        
        @keyframes typing {
            0%, 15% { width: 0 }
            30%, 85% { width: 100% }
            100% { width: 0 }
        }

        @media (max-width: 768px) {
            .typing-text {
                font-size: 1.8rem;
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
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card login-card">
                    <div class="row g-0">
                        <div class="col-md-6">
                            <div class="login-form-container">
                                <div class="login-header">
                                    <i class="fas fa-robot"></i>
                                    <h1>AI Savings Assistant</h1>
                                    <p>Track your finances with AI-powered insights</p>
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
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" name="password" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i> Login
                                    </button>
                                    
                                    <div class="text-center mt-3">
                                        <a href="register.php" class="text-decoration-none">
                                            Don't have an account? Register here
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