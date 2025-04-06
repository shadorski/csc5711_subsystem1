<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 56px);">
        <div class="card content-card p-5" style="max-width: 500px; width: 100%;">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Sign Up</h2>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-3">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form id="signupForm" method="POST" action="/subsystem1/signup">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                        <div id="usernameFeedback" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="" disabled <?php echo !isset($gender) ? 'selected' : ''; ?>>Select Gender</option>
                            <option value="male" <?php echo ($gender ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($gender ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($gender ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="d-flex align-items-center">
                            <input type="password" class="form-control me-2" id="password" name="password" value="<?php echo htmlspecialchars($password ?? ''); ?>" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary btn-lg px-4">Sign Up</button>
                    </div>
                </form>
                <p class="text-center mt-3">
                    Already have an account? <a href="/subsystem1/login" class="text-white">Sign In</a> | 
                    <a href="/subsystem1/" class="text-white">Home</a>
                </p>
            </div>
        </div>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('username').addEventListener('input', function () {
            const username = this.value;
            const feedback = document.getElementById('usernameFeedback');

            if (username.length < 3) {
                feedback.textContent = 'Username must be at least 3 characters.';
                feedback.style.color = '#dc3545';
                return;
            }
            
            fetch('/subsystem1/api/user/check_username', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ username: username })
            })
            .then(response => {
                if (!response.ok) throw new Error('API error');
                return response.json();
            })
            .then(data => {
                if (data.exists) {
                    feedback.textContent = 'Username already taken.';
                    feedback.style.color = '#dc3545';
                } else {
                    feedback.textContent = 'Username available!';
                    feedback.style.color = '#198754';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedback.textContent = 'Error checking username.';
                feedback.style.color = '#dc3545';
            });
        });

        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordField = document.getElementById('password');
            const icon = this.querySelector('svg');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>