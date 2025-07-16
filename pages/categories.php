<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

// Handle insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['name']))) {
    $category_name = trim($_POST['name']);
    $sql = "INSERT INTO categories (name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $category_name);
    $stmt->execute();
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];
    $sql = "DELETE FROM categories WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
}

// Fetch categories
$sql = "SELECT * FROM categories ORDER BY category_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Category Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">

<div class="container mt-5">
  <h3 class="mb-4">📂 Manage Categories</h3>

  <!-- Add Category Form -->
  <form method="POST" class="row g-3 mb-4 shadow-sm p-3 bg-white rounded">
    <div class="col-md-8">
      <input type="text" name="name" class="form-control" placeholder="Enter Category Name" required>
    </div>
    <div class="col-md-4">
      <button class="btn btn-primary w-100">Add Category</button>
    </div>
  </form>

  <!-- Category List -->
  <div class="card shadow-sm">
    <div class="card-body">
      <?php if ($result->num_rows > 0) { ?>
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Category Name</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
              <td><?= $row['category_id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td>
                <button onclick="confirmDelete(<?= $row['category_id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      <?php } else { ?>
        <p class="text-center text-muted">No categories added yet.</p>
      <?php } ?>
    </div>
  </div>
</div>

<script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Are you sure?',
      text: "Category will be deleted permanently!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location = '?delete=' + id;
      }
    })
  }
</script>

</body>
</html>
