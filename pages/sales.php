<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
  body {
    background-color: #f8f9fa;
  }

  .table th,
  .table td {
    vertical-align: middle !important;
  }

  .card-header h5 {
    font-weight: 600;
  }

  .btn-outline-primary {
    border-radius: 50px;
    transition: 0.3s ease;
  }

  .btn-outline-primary:hover {
    background-color: #0d6efd;
    color: #fff;
  }

  .badge.bg-secondary {
    font-size: 0.85rem;
    padding: 0.5em 0.75em;
    border-radius: 0.5rem;
  }
    .hover-shadow:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  }
  .transition {
    transition: all 0.3s ease;
  }
  /* Optional: Make button more rounded and padded */
  .rounded-pill {
    border-radius: 50px;
  }

  /* Add hover effect for table rows */
  tbody tr:hover {
    background-color: #eef2f9;
  }
  </style>
</head>

<body>
  <div class="container my-4">
    <?php include '../components/backToDashboard.php'; ?>
    <div class="card shadow-lg border-0 rounded-3">
      <div
        class="card-header bg-primary text-white d-flex justify-content-between align-items-center px-4 py-3 rounded-top">
        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Sales History</h5>
        <a href="export_sales.php"
          class="btn btn-success d-flex align-items-center shadow-sm hover-shadow transition rounded-pill px-4 py-2">
          <i class="bi bi-file-earmark-excel-fill me-2"></i> Export to Excel
        </a>
      </div>
      <div class="card-body p-4">
        <!-- Optional: Add search bar -->
        <!--
        <div class="mb-3">
          <input type="text" class="form-control" placeholder="Search sales..." id="searchInput" onkeyup="filterTable()">
        </div>
        -->
        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle text-center" id="salesTable">
            <thead class="table-dark">
              <tr>
                <th>Invoice ID</th>
                <th>Customer Name</th>
                <th>Date & Time</th>
                <th>Total Amount</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              require_once '../includes/db.php';
              $sql = "SELECT * FROM sales ORDER BY sale_date DESC";
              $result = $conn->query($sql);
              if ($result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
              ?>
              <tr>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['invoice_id']); ?></span></td>
                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                <td><?php echo date('d-m-Y H:i:s', strtotime($row['sale_date'])); ?></td>
                <td><strong>₹<?php echo number_format($row['total_amount'], 2); ?></strong></td>
                <td>
                  <a href="view_invoice.php?invoice_id=<?php echo urlencode($row['invoice_id']); ?>"
                    class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye"></i> View
                  </a>
                </td>
              </tr>
              <?php
                endwhile;
              else:
                echo "<tr><td colspan='5' class='text-muted'>No sales history available.</td></tr>";
              endif;
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <!-- Optional: Include scripts for search/filter -->
  <!--
  <script>
    function filterTable() {
      const input = document.getElementById('searchInput');
      const filter = input.value.toLowerCase();
      const table = document.getElementById('salesTable');
      const tr = table.getElementsByTagName('tr');
      for (let i = 1; i < tr.length; i++) {
        tr[i].style.display = 'none';
        const tdArray = tr[i].getElementsByTagName('td');
        for (let j = 0; j < tdArray.length; j++) {
          if (tdArray[j]) {
            if (tdArray[j].innerText.toLowerCase().indexOf(filter) > -1) {
              tr[i].style.display = '';
              break;
            }
          }
        }
      }
    }
  </script>
  -->
</body>

</html>