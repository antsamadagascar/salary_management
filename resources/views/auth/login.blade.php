<!-- resources/views/auth/login.blade.php -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a69bd;
            --secondary-color: #6a89cc;
            --accent-color: #f6b93b;
            --light-bg: #f5f6fa;
            --dark-text: #2c3e50;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .login-container {
            flex: 1;
            padding: 2rem 0;
        }
        
        .card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-size: 1.5rem;
            padding: 1rem;
            text-align: center;
            border-bottom: none;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #dcdde1;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(74, 105, 189, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .forgot-password a:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        .login-icon {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .card {
                border-radius: 0;
                box-shadow: none;
            }
            
            .login-container {
                padding: 0;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Connexion</div>
                        <div class="card-body">
                            <div class="login-icon">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            
                            @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                            
                            @if (session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                            @endif
                            
                            <form method="POST" action="{{ route('login') }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="usr" class="form-label">Nom d'utilisateur</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="usr" name="usr" value="{{ old('usr') }}" required autofocus>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="pwd" class="form-label">Mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="pwd" name="pwd" required>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>