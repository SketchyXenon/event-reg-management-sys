<?php
// ============================================================
//  register.php
//  Handles student registration form submission
//  Security: PDO prepared statements + password_hash + validation
//  Event Registration Management System
// ============================================================

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/password_helper.php';

$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Collect & sanitize inputs ──────────────────────────
    $full_name  = trim(htmlspecialchars($_POST['full_name']  ?? '', ENT_QUOTES, 'UTF-8'));
    $student_id = trim(htmlspecialchars($_POST['student_id'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email      = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password   = $_POST['password']         ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    // ── 2. Server-side validation ──────────────────────────────
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) < 2 || strlen($full_name) > 150) {
        $errors[] = 'Full name must be between 2 and 150 characters.';
    }

    if (empty($student_id)) {
        $errors[] = 'Student ID is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-]+$/', $student_id)) {
        $errors[] = 'Student ID may only contain letters, numbers, and hyphens.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } else {
        // Use bcrypt strength validator
        $strength = validate_password_strength($password);
        if (!$strength['valid']) {
            $errors = array_merge($errors, $strength['errors']);
        }
    }

    if ($password !== $confirm_pw) {
        $errors[] = 'Passwords do not match.';
    }

    // ── 3. Check for duplicate email / student ID ──────────────
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "SELECT user_id FROM users
                  WHERE email = :email OR student_id = :student_id
                  LIMIT 1"
            );
            $stmt->execute([
                ':email'      => $email,
                ':student_id' => $student_id,
            ]);

            if ($stmt->rowCount() > 0) {
                $errors[] = 'An account with that email or student ID already exists.';
            }
        } catch (PDOException $e) {
            error_log("Register check error: " . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }

    // ── 4. Insert into database ────────────────────────────────
    if (empty($errors)) {
        try {
            // Hash password using bcrypt via password_helper
            $hashed_password = hash_password($password);

            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, student_id, email, password, role)
                 VALUES (:full_name, :student_id, :email, :password, 'student')"
            );
            $stmt->execute([
                ':full_name'  => $full_name,
                ':student_id' => $student_id,
                ':email'      => $email,
                ':password'   => $hashed_password,
            ]);

            $success = true;

            // Auto-login after successful registration
            $_SESSION['user_id']   = $pdo->lastInsertId();
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role']      = 'student';

            // Redirect to dashboard
            header("Location: ../dashboard.php?registered=1");
            exit();

        } catch (PDOException $e) {
            error_log("Registration insert error: " . $e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Event Registration System</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="form-container">
  <h2>Create an Account</h2>
  <p class="subtitle">Register to browse and join events</p>

  <!-- Error messages -->
  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form action="register.php" method="POST" novalidate>

    <div class="form-group">
      <label for="full_name">Full Name</label>
      <input type="text" id="full_name" name="full_name"
             value="<?= htmlspecialchars($full_name ?? '') ?>"
             placeholder="Juan Dela Cruz" required>
    </div>

    <div class="form-group">
      <label for="student_id">Student ID</label>
      <input type="text" id="student_id" name="student_id"
             value="<?= htmlspecialchars($student_id ?? '') ?>"
             placeholder="e.g. 2024-00123" required>
    </div>

    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($email ?? '') ?>"
             placeholder="you@school.edu.ph" required>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             placeholder="At least 8 chars, 1 uppercase, 1 number" required>
    </div>

    <div class="form-group">
      <label for="confirm_password">Confirm Password</label>
      <input type="password" id="confirm_password" name="confirm_password"
             placeholder="Re-enter your password" required>
    </div>

    <button type="submit" class="btn-primary">Register</button>

    <p class="form-footer">Already have an account? <a href="login.php">Log in here</a></p>

  </form>
</div>

<script src="../assets/js/validate.js"></script>
</body>
</html>