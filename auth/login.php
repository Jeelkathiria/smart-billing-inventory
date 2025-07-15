<?php session_start();?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Smart Billing</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-body-tertiary d-flex align-items-center justify-content-center position-relative" style="min-height:100vh;">
  <div class="position-fixed top-0 start-0 w-100 h-100" style="
    z-index:0;
    opacity:0.10;
    background-repeat:repeat;
    background-size:400px 250px;
    background-image: url('data:image/svg+xml;utf8,<svg width=\'400\' height=\'250\' xmlns=\'http://www.w3.org/2000/svg\'><g opacity=\'1\'><rect x=\'20\' y=\'20\' width=\'60\' height=\'36\' rx=\'8\' fill=\'%234f46e5\'/><rect x=\'120\' y=\'40\' width=\'40\' height=\'40\' rx=\'8\' fill=\'%234fd1c5\'/><rect x=\'200\' y=\'30\' width=\'70\' height=\'28\' rx=\'6\' fill=\'%23fbbf24\'/><rect x=\'300\' y=\'60\' width=\'50\' height=\'20\' rx=\'6\' fill=\'%2393c5fd\'/><rect x=\'60\' y=\'140\' width=\'70\' height=\'28\' rx=\'8\' fill=\'%23f472b6\'/><circle cx=\'180\' cy=\'200\' r=\'18\' fill=\'%234ade80\'/><rect x=\'250\' y=\'170\' width=\'80\' height=\'40\' rx=\'10\' fill=\'%23a3e635\'/><g><rect x=\'320\' y=\'30\' width=\'40\' height=\'16\' rx=\'3\' fill=\'%23fbbf24\'/><rect x=\'325\' y=\'35\' width=\'30\' height=\'2\' fill=\'%23fff\'/><rect x=\'325\' y=\'39\' width=\'20\' height=\'2\' fill=\'%23fff\'/></g><g><rect x=\'60\' y=\'80\' width=\'36\' height=\'36\' rx=\'8\' fill=\'%23f472b6\'/><rect x=\'70\' y=\'90\' width=\'16\' height=\'4\' fill=\'%23fff\'/><rect x=\'70\' y=\'98\' width=\'20\' height=\'4\' fill=\'%23fff\'/></g><g><rect x=\'160\' y=\'120\' width=\'60\' height=\'20\' rx=\'4\' fill=\'%2393c5fd\'/><rect x=\'170\' y=\'125\' width=\'40\' height=\'3\' fill=\'%23fff\'/><rect x=\'170\' y=\'131\' width=\'25\' height=\'3\' fill=\'%23fff\'/></g><g><rect x=\'220\' y=\'80\' width=\'30\' height=\'30\' rx=\'6\' fill=\'%234fd1c5\'/><rect x=\'227\' y=\'87\' width=\'16\' height=\'3\' fill=\'%23fff\'/><rect x=\'227\' y=\'93\' width=\'10\' height=\'3\' fill=\'%23fff\'/></g><g><rect x=\'320\' y=\'120\' width=\'40\' height=\'16\' rx=\'3\' fill=\'%23fbbf24\'/><rect x=\'325\' y=\'125\' width=\'30\' height=\'2\' fill=\'%23fff\'/><rect x=\'325\' y=\'129\' width=\'20\' height=\'2\' fill=\'%23fff\'/></g></g></svg>');
  "></div>
  <div class="container position-relative" style="z-index:1;">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-8 col-md-6 col-lg-5 col-xl-4">
        <div class="card shadow-lg border-0 rounded-4 p-4 my-5">
          <div class="text-center mb-3">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-gradient"
              style="width:60px;height:60px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="white" class="bi bi-receipt"
                viewBox="0 0 16 16">
                <path
                  d="M1.92.506a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627A.5.5 0 0 1 15 1v14a.5.5 0 0 1-.79.407l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627A.5.5 0 0 1 1 15V1a.5.5 0 0 1 .92-.494ZM2 1.934v12.132l.44-.293a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.44.293V1.934l-.44.293a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0L2 1.934Z" />
                <path
                  d="M3 4.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5Zm0 2a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5Zm0 2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Z" />
              </svg>
            </div>
          </div>
          <h4 class="mb-3 text-center fw-semibold text-primary">Smart Billing & Inventory Login</h4>
          <?php if (isset($_GET['error'])): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
          <?php endif; ?>
          <form action="process_login.php" method="POST">
            <div class="mb-3">
              <input type="text" name="username" class="form-control form-control-lg" placeholder="Username" required>
            </div>
            <div class="mb-3">
              <input type="password" name="password" class="form-control form-control-lg" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Login</button>
          </form>
          <div class="mt-3 text-center text-muted small">
            &copy; <?= date('Y') ?> Smart Billing
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>