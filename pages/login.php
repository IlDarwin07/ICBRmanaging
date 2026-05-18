<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}
$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ICBR Gestionale</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <i class="fas fa-futbol auth-icon"></i>
                <h1>Interclub Brindisi</h1>
                <p>Gestionale Soci</p>
            </div>

            <div class="auth-tabs">
                <button class="tab-btn active" onclick="showTab('login')">Accedi</button>
                <button class="tab-btn" onclick="showTab('register')">Registrati</button>
            </div>

            <div id="message" class="message hidden"></div>

            <form id="loginForm" class="auth-form" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label for="login-username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="login-username" name="username" required placeholder="Il tuo username">
                </div>
                <div class="form-group">
                    <label for="login-password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="login-password" name="password" required placeholder="La tua password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Accedi
                </button>
            </form>

            <form id="registerForm" class="auth-form hidden" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label for="reg-nome"><i class="fas fa-user"></i> Nome</label>
                    <input type="text" id="reg-nome" name="nome" required placeholder="Il tuo nome">
                </div>
                <div class="form-group">
                    <label for="reg-cognome"><i class="fas fa-user"></i> Cognome</label>
                    <input type="text" id="reg-cognome" name="cognome" required placeholder="Il tuo cognome">
                </div>
                <div class="form-group">
                    <label for="reg-email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="reg-email" name="email" required placeholder="La tua email">
                </div>
                <div class="form-group">
                    <label for="reg-username"><i class="fas fa-at"></i> Username</label>
                    <input type="text" id="reg-username" name="username" required placeholder="Scegli un username">
                </div>
                <div class="form-group">
                    <label for="reg-password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="reg-password" name="password" required minlength="6" placeholder="Minimo 6 caratteri">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Registrati
                </button>
            </form>

            <div class="auth-footer">
                <p>Credenziali admin di default: <strong>admin</strong> / <strong>admin123</strong></p>
            </div>
        </div>
    </div>

    <script>
    function showTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('loginForm').classList.toggle('hidden', tab !== 'login');
        document.getElementById('registerForm').classList.toggle('hidden', tab !== 'register');
        event.target.classList.add('active');
        hideMessage();
    }

    function showMessage(text, type) {
        const msg = document.getElementById('message');
        msg.textContent = text;
        msg.className = 'message message-' + type;
    }

    function hideMessage() {
        document.getElementById('message').className = 'message hidden';
    }

    function handleLogin(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        fetch('/api/login.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/pages/dashboard.php';
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(() => showMessage('Errore di connessione', 'error'));
    }

    function handleRegister(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        fetch('/api/register.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => showTab('login'), 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(() => showMessage('Errore di connessione', 'error'));
    }
    </script>
</body>
</html>
