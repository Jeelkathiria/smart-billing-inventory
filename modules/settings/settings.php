<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$user_id  = $_SESSION['user_id'] ?? null;
$store_id = $_SESSION['store_id'] ?? null;

if (!$user_id || !$store_id) {
  header("Location: /auth/index.php");
  exit;
}

$stmt = $conn->prepare("SELECT username, role, personal_contact_number FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$role = $user['role'] ?? '';
$admin_contact = $user['personal_contact_number'] ?? '';

$cols = ['store_name','store_email','contact_number','store_code','gstin','billing_fields'];
$resCols = $conn->query("SHOW COLUMNS FROM stores LIKE 'store_address'");
if ($resCols && $resCols->num_rows) $cols[] = 'store_address';
$resCols = $conn->query("SHOW COLUMNS FROM stores LIKE 'note'");
if ($resCols && $resCols->num_rows) $cols[] = 'note';
// Fallback: keep 'notice' if present (backward compatibility)
$resCols = $conn->query("SHOW COLUMNS FROM stores LIKE 'notice'");
if ($resCols && $resCols->num_rows && !in_array('notice', $cols)) $cols[] = 'notice';
$colSql = implode(', ', $cols);
$stmt = $conn->prepare("SELECT $colSql FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$billing_fields = json_decode($store['billing_fields'] ?? '{}', true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/assets/css/common.css">
  <style>
  body {
    margin-top: 10vh;
    background: #f5f6fa;
  }

  .settings-container {
    max-width: 900px;
    margin: 40px auto;
  }

  .card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
  }

  .card-header {
    background: #fff;
    font-weight: 600;
    font-size: 1.05rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  </style>
  <style>
  /* Delete modal inline message styling */
  .delete-modal-msg { margin-bottom: 8px; }
  .delete-modal-msg .alert { margin-bottom: 0; }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <main class="settings-container container mt-4">
    <!-- ===================== TAB NAVIGATION ===================== -->
    <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal"
          type="button"><i class="bi bi-person-circle"></i> Personal</button>
      </li>
      <?php if ($role === 'admin'): ?>
      <li class="nav-item">
        <button class="nav-link" id="store-tab" data-bs-toggle="tab" data-bs-target="#store" type="button"><i
            class="bi bi-shop"></i>
          Store</button>
      </li>
      <?php endif; ?>
    </ul>

    <!-- ===================== TAB CONTENT ===================== -->
    <div class="tab-content" id="settingsTabsContent">
      <!-- ========== PERSONAL TAB ========== -->
      <div class="tab-pane fade show active" id="personal">
        <!-- PROFILE CARD -->
        <div class="card mb-3">
          <div class="card-header">
            <span><i class="bi bi-person-fill"></i> Profile Information</span>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
              <i class="bi bi-pencil-square"></i> Edit
            </button>
          </div>
          <div class="card-body">
            <p><strong>Name:</strong> <span data-user-name><?= htmlspecialchars($user['username']) ?></span></p>
            <p><strong>Role:</strong> <?= htmlspecialchars($user['role']) ?></p>
          </div>
        </div>

        <!-- CHANGE PASSWORD CARD -->
        <div class="card">
          <div class="card-header">
            <span><i class="bi bi-shield-lock"></i> Change Password</span>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
              <i class="bi bi-key"></i> Change
            </button>
          </div>
          <div class="card-body">
            <p>Keep your account secure by changing your password regularly.</p>
          </div>
        </div>
      </div>

      <!-- ========== STORE TAB ========== -->
      <?php if ($role === 'admin'): ?>
      <div class="tab-pane fade" id="store">
        <!-- STORE INFO -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-shop"></i> Store Information</span>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editStoreInfoModal">
              <i class="bi bi-pencil-square"></i> Edit
            </button>
          </div>

          <div class="card-body">
            <p>
              <strong>Store Name:</strong> <span data-store-name><?= htmlspecialchars($store['store_name'] ?? '') ?></span>
            </p>

            <p><strong>Store Code:</strong>
              <span id="storeCode"><?= htmlspecialchars($store['store_code'] ?? '') ?></span>
              <i id="copyIcon" class="bi bi-clipboard ms-2 text-primary" style="cursor: pointer;" title="Copy"
                onclick="copyStoreCode()"></i>
            </p>

            <p><strong>Email:</strong> <span data-store-email><?= htmlspecialchars($store['store_email'] ?? '') ?></span></p>
           
            <p><strong>Contact:</strong> <span data-admin-contact><?= htmlspecialchars($admin_contact ?? $store['contact_number'] ?? '') ?></span>
              <small class="text-muted"> (account contact)</small></p>

            <p>
              <strong>GSTIN:</strong>
              <span data-gstin><?php if (!empty($store['gstin'])): ?>
              <?= htmlspecialchars($store['gstin'] ?? '') ?>
              <?php else: ?>
              <span class="text-muted">-- Not entered --</span>
              <?php endif; ?></span>
              <?php if (empty($store['gstin'])): ?>
              <i class="bi bi-info-circle-fill text-warning ms-2" data-bs-toggle="tooltip"
                title="Enter GSTIN to appear on the bill"></i>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <!-- INVOICE DETAILS -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt"></i> Invoice Details</span>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editInvoiceModal">
              <i class="bi bi-pencil-square"></i> Edit
            </button>
          </div>
          <div class="card-body">
            <p><strong>Store Address:</strong> <span data-store-address><?= htmlspecialchars($store['store_address'] ?? '') ?></span></p>
            <p><strong>Store Contact:</strong> <span data-store-contact><?= htmlspecialchars($store['contact_number'] ?? '') ?></span></p>
            <p><strong>Note:</strong> <span data-store-note><?= htmlspecialchars($store['note'] ?? $store['notice'] ?? '') ?></span></p>
          </div>
        </div>

        <!-- BILLING FIELDS -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt-cutoff"></i> Billing Fields</span>
            <i class="bi bi-info-circle text-primary" data-bs-toggle="tooltip" data-bs-placement="left"
              title="Don't forget to save otherwise changes won't be applied." style="cursor: help;"></i>
          </div>

          <div class="card-body">
            <form id="billingFieldsForm">
              <input type="hidden" name="full_update" value="1">
              <?php
              $fields = [
                'customer_name' => 'Customer Name',
                'customer_mobile' => 'Customer Mobile',
                'customer_email' => 'Customer Email',
                'customer_address' => 'Customer Address'
              ];
              foreach ($fields as $key => $label):
              ?>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="fields[<?= $key ?>]"
                  <?= !empty($billing_fields[$key]) ? 'checked' : '' ?>>
                <label class="form-check-label"><?= $label ?></label>
              </div>
              <?php endforeach; ?>
              <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
            </form>
          </div>
        </div>

         <!-- DELETE ACCOUNT CARD -->
          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="bi bi-trash2 text-danger"></i> Delete Account</span>
              <button id="deleteBtn" class="btn btn-danger btn-sm">Delete Account</button>
            </div>
            <div class="card-body">
              <p class="mb-0 text-muted">Permanent deletion of store data and account. This action is irreversible.</p>
            </div>
          </div>

      </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- ===================== EDIT PROFILE MODAL ===================== -->
  <div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="profileForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Edit Profile</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="delete-modal-msg"></div>
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <!-- Email and Contact are not editable here as per policy - only Name is editable -->
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== CHANGE PASSWORD MODAL ===================== -->
  <div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="passwordForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-shield-lock-fill"></i> Change Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Step 1: Verify current password -->
            <div id="verifyStep">
              <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-control" name="current_password" required>
              </div>
              <button type="button" class="btn btn-primary w-100" id="verifyBtn">Verify</button>
            </div>

            <!-- Step 2: New password (hidden) -->
            <div id="updateStep" style="display:none;">
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="new_password" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
              </div>
              <button type="button" class="btn btn-success w-100" id="updateBtn">Update Password</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== EDIT STORE INFO MODAL (Name + GSTIN) ===================== -->
  <div class="modal fade" id="editStoreInfoModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="storeInfoForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-shop"></i> Edit Store</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Store Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="store_name"
                value="<?= htmlspecialchars($store['store_name'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Store Email <span class="text-muted">(Read-only)</span></label>
              <input type="email" class="form-control" name="store_email"
                value="<?= htmlspecialchars($store['store_email'] ?? '') ?>" readonly>
              <small class="text-muted">Contact admin to change email</small>
            </div>
            <div class="mb-3">
              <label class="form-label">GSTIN <span class="text-muted">(Optional)</span></label>
              <input type="text" class="form-control" name="gstin" 
                value="<?= htmlspecialchars($store['gstin'] ?? '') ?>" 
                placeholder="15 character GSTIN">
            </div>
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== EDIT INVOICE DETAILS MODAL (Address / Contact / Note) ===================== -->
  <div class="modal fade" id="editInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="invoiceForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-receipt"></i> Edit Invoice Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
                    <div class="mb-3">
                      <label class="form-label">Store Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="store_name" value="<?= htmlspecialchars($store['store_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Store Contact Number</label>
                      <input type="text" class="form-control" name="contact_number" 
                        value="<?= htmlspecialchars($store['contact_number'] ?? '') ?>">
              <small class="text-muted">This contact will be printed on invoices if provided.</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Store Address</label>
              <textarea class="form-control" name="store_address" rows="2"><?= htmlspecialchars($store['store_address'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Note <span class="text-muted">(Appears at bottom of invoice)</span></label>
              <textarea class="form-control" name="note" rows="2"><?= htmlspecialchars($store['note'] ?? $store['notice'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== JS ===================== -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // Delete Account - Modal + Multi-step UI
  (function() {
    const deleteBtn = document.getElementById('deleteBtn');
    if (!deleteBtn) return;
    // Build modal HTML and append to body
    const modalHtml = `
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-trash2 text-danger"></i> Delete Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="stepWarning">
              <p class="text-danger fw-bold">Warning: This action will permanently delete your store and all associated data.</p>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="chkUnderstand">
                <label class="form-check-label">I understand this is permanent</label>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="chkAgree">
                <label class="form-check-label">I agree that all data will be deleted</label>
              </div>
              <button class="btn btn-danger w-100" id="toReasonStep" disabled>Continue</button>
            </div>

            <div id="stepReason" style="display:none;">
              <p>Select a reason:</p>
              <div class="form-check"><input class='form-check-input' type='radio' name='reason' value='Business closed' id='r1'><label class='form-check-label' for='r1'>Business closed</label></div>
              <div class="form-check"><input class='form-check-input' type='radio' name='reason' value='Switching to another software' id='r2'><label class='form-check-label' for='r2'>Switching to another software</label></div>
              <div class="form-check"><input class='form-check-input' type='radio' name='reason' value='Testing purpose' id='r3'><label class='form-check-label' for='r3'>Testing purpose</label></div>
              <div class="form-check"><input class='form-check-input' type='radio' name='reason' value='Too complex to use' id='r4'><label class='form-check-label' for='r4'>Too complex to use</label></div>
              <div class="form-check"><input class='form-check-input' type='radio' name='reason' value='Other' id='r5'><label class='form-check-label' for='r5'>Other</label></div>
              <textarea id='otherReason' class='form-control mt-2' placeholder='Please specify...' style='display:none;'></textarea>
              <div class='d-flex gap-2 mt-3'><button class='btn btn-secondary' id='backToWarning'>Back</button><button class='btn btn-primary' id='toPasswordStep'>Next</button></div>
            </div>

            <div id='stepPassword' style='display:none;'>
              <p>Re-enter your password to continue:</p>
              <input class='form-control mb-2' type='password' id='confirmPassword' placeholder='Current password'>
              <div class='d-flex gap-2'><button class='btn btn-secondary' id='backToReason'>Back</button><button class='btn btn-primary' id='checkPasswordBtn'>Verify Password</button></div>
            </div>

            <div id='stepOTP' style='display:none;'>
              <p>An OTP will be sent to your store email to confirm the deletion.</p>
              <div class='mb-2'><button class='btn btn-outline-primary w-100' id='sendOtpBtn'>Send OTP</button></div>
              <input class='form-control mb-2' type='text' id='deleteOtp' placeholder='Enter OTP'>
              <div class='d-flex gap-2'><button class='btn btn-secondary' id='backToPassword'>Back</button><button class='btn btn-primary' id='verifyOtpBtn'>Verify OTP</button></div>
            </div>

            <div id='stepFinal' style='display:none;'>
              <p class='text-danger'>Final Confirmation: Type <span class='fw-bold'>DELETE MY ACCOUNT</span> to permanently delete.</p>
              <input class='form-control' id='finalConfirmText' placeholder='DELETE MY ACCOUNT'>
              <div class='d-flex gap-2 mt-3'><button class='btn btn-secondary' id='backToOtp'>Back</button><button class='btn btn-danger' id='doDeleteBtn' disabled>Delete My Account</button></div>
            </div>

            <div id='stepLoading' style='display:none; text-align:center;'>
              <div class='spinner-border text-danger' role='status'></div>
              <p class='mt-2'>Deleting your data...</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalEl = document.getElementById('deleteAccountModal');
    const modal = new bootstrap.Modal(modalEl);

    const chkUnderstand = modalEl.querySelector('#chkUnderstand');
    const chkAgree = modalEl.querySelector('#chkAgree');
    const toReasonStep = modalEl.querySelector('#toReasonStep');
    const toPasswordStep = modalEl.querySelector('#toPasswordStep');
    const backToWarning = modalEl.querySelector('#backToWarning');
    const backToReason = modalEl.querySelector('#backToReason');
    const backToPassword = modalEl.querySelector('#backToPassword');
    const sendOtpBtn = modalEl.querySelector('#sendOtpBtn');
    const checkPasswordBtn = modalEl.querySelector('#checkPasswordBtn');
    const verifyOtpBtn = modalEl.querySelector('#verifyOtpBtn');
    const doDeleteBtn = modalEl.querySelector('#doDeleteBtn');
    const finalConfirmText = modalEl.querySelector('#finalConfirmText');
    const otherReason = modalEl.querySelector('#otherReason');

    // toggles
    chkUnderstand.addEventListener('change', () => { toReasonStep.disabled = !(chkUnderstand.checked && chkAgree.checked); if (!chkUnderstand.checked || !chkAgree.checked) toPasswordStep.disabled = true; });
    chkAgree.addEventListener('change', () => { toReasonStep.disabled = !(chkUnderstand.checked && chkAgree.checked); if (!chkUnderstand.checked || !chkAgree.checked) toPasswordStep.disabled = true; });
    // Disable 'toPasswordStep' until a reason is selected
    toPasswordStep.disabled = true;
    // Disable 'toReasonStep' until checkboxes are acknowledged
    toReasonStep.disabled = true;
    toReasonStep.addEventListener('click', () => { document.getElementById('stepWarning').style.display='none'; document.getElementById('stepReason').style.display='block'; });
    backToWarning.addEventListener('click', () => { document.getElementById('stepReason').style.display='none'; document.getElementById('stepWarning').style.display='block'; });
    toPasswordStep.addEventListener('click', () => {
      const reasonSel = modalEl.querySelector('input[name="reason"]:checked');
      if (!reasonSel) return showDeleteModalMessage(modalEl, 'Please select a reason to continue', 'danger');
      if (reasonSel.value === 'Other') {
        const other = otherReason.value.trim();
        if (!other) return showDeleteModalMessage(modalEl, 'Please specify the reason in the text box', 'danger');
      }
      document.getElementById('stepReason').style.display='none';
      document.getElementById('stepPassword').style.display='block';
      setTimeout(()=>{ const pwdEl = modalEl.querySelector('#confirmPassword'); if (pwdEl) pwdEl.focus(); }, 50);
    });
    backToReason.addEventListener('click', () => { document.getElementById('stepPassword').style.display='none'; document.getElementById('stepReason').style.display='block'; });
    backToPassword.addEventListener('click', () => { document.getElementById('stepOTP').style.display='none'; document.getElementById('stepPassword').style.display='block'; });

    // reason radio controls
    modalEl.querySelectorAll('input[name="reason"]').forEach(r => r.addEventListener('change', e => {
      otherReason.style.display = (e.target.value === 'Other') ? 'block' : 'none';
      // enable the continue button when a reason is selected
      toPasswordStep.disabled = false;
    }));

    // Delete modal inline message helper
    function showDeleteModalMessage(modalEl, message, type = 'danger') {
      let msgBox = modalEl.querySelector('.delete-modal-msg');
      if (!msgBox) {
        msgBox = document.createElement('div');
        msgBox.className = 'delete-modal-msg mt-2';
        modalEl.querySelector('.modal-body').prepend(msgBox);
      }
      msgBox.innerHTML = `<div class="alert alert-${type} py-2 px-3 mb-2">${message}</div>`;
      setTimeout(() => { if (msgBox) msgBox.innerHTML = ''; }, 5000);
    }

    checkPasswordBtn.addEventListener('click', () => {
      const pwd = modalEl.querySelector('#confirmPassword').value.trim();
      if (!pwd) return showDeleteModalMessage(modalEl, 'Please enter your password', 'danger');
      checkPasswordBtn.disabled = true;
      fetch('/modules/settings/delete_account.php', { method:'POST', credentials: 'include', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, body:'action=check_password&password='+encodeURIComponent(pwd) })
      .then(r=>r.json()).then(data=>{
        checkPasswordBtn.disabled = false;
        if (data.status === 'ok') {
          document.getElementById('stepPassword').style.display='none';
          document.getElementById('stepOTP').style.display='block';
          setTimeout(()=>{ const otpBtn = modalEl.querySelector('#sendOtpBtn'); if (otpBtn) otpBtn.focus(); }, 50);
        } else showDeleteModalMessage(modalEl, data.message || 'Password check failed', 'danger');
      }).catch((err)=>{ checkPasswordBtn.disabled = false; console.error('Check password request error', err); showDeleteModalMessage(modalEl, 'Network or server error. Please login and try again.', 'danger'); if (window.showGlobalToast) showGlobalToast('Network or server error. Please try again.','danger',2000); });
    });

    sendOtpBtn.addEventListener('click', () => {
      sendOtpBtn.disabled = true;
      fetch('/modules/settings/delete_account.php', { method:'POST', credentials: 'include', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, body:'action=send_otp' })
        .then(r=>r.json()).then(data=>{ 
          sendOtpBtn.disabled = false; 
          if (data.status === 'ok') showDeleteModalMessage(modalEl, 'OTP sent', 'success'); 
          else showDeleteModalMessage(modalEl, data.message || 'Failed to send OTP', 'danger'); 
        })
        .catch((err)=>{ sendOtpBtn.disabled = false; console.error('Send OTP request error', err); showDeleteModalMessage(modalEl, 'Network or server error. Please login and try again.', 'danger'); if (window.showGlobalToast) showGlobalToast('Network or server error. Please try again.','danger',2000); });
    });

    verifyOtpBtn.addEventListener('click', () => {
      const otp = modalEl.querySelector('#deleteOtp').value.trim();
      if (!otp) return showDeleteModalMessage(modalEl, 'Enter the OTP', 'danger');
      verifyOtpBtn.disabled = true;
      fetch('/modules/settings/delete_account.php', { method:'POST', credentials: 'include', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, body:'action=verify_otp&otp='+encodeURIComponent(otp) })
        .then(r=>r.json()).then(data=>{ 
          verifyOtpBtn.disabled = false; 
          if (data.status === 'ok') { document.getElementById('stepOTP').style.display='none'; document.getElementById('stepFinal').style.display='block'; setTimeout(()=>{ const finalEl = modalEl.querySelector('#finalConfirmText'); if (finalEl) finalEl.focus(); }, 50); } 
          else showDeleteModalMessage(modalEl, data.message || 'Invalid OTP', 'danger'); 
        })
        .catch((err)=>{ verifyOtpBtn.disabled = false; console.error('Verify OTP request error', err); showDeleteModalMessage(modalEl, 'Network or server error. Please login and try again.', 'danger'); });
    });

    finalConfirmText.addEventListener('input', () => { doDeleteBtn.disabled = finalConfirmText.value.trim() !== 'DELETE MY ACCOUNT'; });

    doDeleteBtn.addEventListener('click', () => {
      const reasonSel = modalEl.querySelector('input[name="reason"]:checked');
      const reason = reasonSel ? reasonSel.value : '';
      const other = otherReason.value.trim();
      const pwd = modalEl.querySelector('#confirmPassword').value.trim();
      // Validate again before final deletion
      if (!reasonSel) { showDeleteModalMessage(modalEl, 'Please select a reason for deletion', 'danger'); return; }
      if (reasonSel.value === 'Other' && !other) { showDeleteModalMessage(modalEl, 'Please enter details for Other reason', 'danger'); return; }
      if (!pwd) { showDeleteModalMessage(modalEl, 'Please enter your password', 'danger'); return; }
      doDeleteBtn.disabled = true;
      document.getElementById('stepFinal').style.display='none'; document.getElementById('stepLoading').style.display='block';
      const body = `action=delete_account&password=${encodeURIComponent(pwd)}&reason=${encodeURIComponent(reason)}&other_reason=${encodeURIComponent(other)}&final_confirm=${encodeURIComponent(finalConfirmText.value.trim())}`;
      setTimeout(()=>{ // short delay for UX
        fetch('/modules/settings/delete_account.php', { method:'POST', credentials: 'include', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, body })
        .then(r=>r.json()).then(data=>{
          if (data.status === 'ok') {
            showDeleteModalMessage(modalEl, 'Account deleted. You will be redirected.', 'success');
            setTimeout(() => window.location.href = '/auth/index.php?message=account_deleted', 1400);
          } else {
            showDeleteModalMessage(modalEl, data.message || 'Delete failed', 'danger');
            doDeleteBtn.disabled = false;
            document.getElementById('stepLoading').style.display='none';
            document.getElementById('stepFinal').style.display='block';
          }
        }).catch((err)=>{ console.error('Delete account request error', err); showDeleteModalMessage(modalEl, 'Network or server error. Please login and try again.', 'danger'); if (window.showGlobalToast) showGlobalToast('Network or server error. Please try again.','danger',2000); doDeleteBtn.disabled = false; document.getElementById('stepLoading').style.display='none'; document.getElementById('stepFinal').style.display='block'; });
      }, 700);
    });

    // open modal: reset all steps and messages
    deleteBtn.addEventListener('click', () => {
      // Clear all inputs and resets
      chkUnderstand.checked = false;
      chkAgree.checked = false;
      toReasonStep.disabled = true;
      toPasswordStep.disabled = true;
      document.getElementById('stepWarning').style.display = 'block';
      document.getElementById('stepReason').style.display = 'none';
      document.getElementById('stepPassword').style.display = 'none';
      document.getElementById('stepOTP').style.display = 'none';
      document.getElementById('stepFinal').style.display = 'none';
      document.getElementById('stepLoading').style.display = 'none';
      // reset fields
      modalEl.querySelector('#confirmPassword').value = '';
      modalEl.querySelector('#deleteOtp').value = '';
      modalEl.querySelector('#finalConfirmText').value = '';
      modalEl.querySelectorAll('input[name="reason"]').forEach(r => r.checked = false);
      otherReason.value = '';
      if (modalEl.querySelector('.delete-modal-msg')) modalEl.querySelector('.delete-modal-msg').innerHTML = '';
      modal.show();
    });
  })();

  /* ============================================================
   ✅ UNIVERSAL FORM HANDLER SYSTEM (Profile / Store / Billing)
============================================================ */
  function showModalMessage(form, message, type = 'success') {
    let msgBox = form.querySelector('.modal-msg');
    if (!msgBox) {
      msgBox = document.createElement('div');
      msgBox.className = 'modal-msg mt-3';
      form.appendChild(msgBox);
    }

    msgBox.innerHTML = `
    <div class="alert alert-${type} py-2 px-3 mb-2">${message}</div>
  `;
    setTimeout(() => (msgBox.innerHTML = ''), 4000);
  }

  // ✅ JS Validation for Store Info (only store name + GSTIN)
  function validateStoreInfoForm(form) {
    const storeName = form.querySelector('[name="store_name"]').value.trim();
    const gstin = form.querySelector('[name="gstin"]').value.trim();

    if (!storeName) {
      showModalMessage(form, 'Store name is required', 'danger');
      form.querySelector('[name="store_name"]').focus();
      return false;
    }

    if (gstin && gstin.length !== 15) {
      showModalMessage(form, 'GSTIN must be exactly 15 characters', 'danger');
      form.querySelector('[name="gstin"]').focus();
      return false;
    }

    if (gstin && !/^[0-9A-Z]{15}$/.test(gstin)) {
      showModalMessage(form, 'GSTIN must contain only letters (A-Z) and numbers (0-9)', 'danger');
      form.querySelector('[name="gstin"]').focus();
      return false;
    }

    return true;
  }

  // ✅ JS Validation for Invoice Details (contact / address / note)
  function validateInvoiceForm(form) {
    const storeName = form.querySelector('[name="store_name"]').value.trim();
    if (!storeName) {
      showModalMessage(form, 'Store name is required', 'danger');
      form.querySelector('[name="store_name"]').focus();
      return false;
    }
    const contact = form.querySelector('[name="contact_number"]').value.trim();
    if (contact && !/^\+?[0-9\-\s]{7,20}$/.test(contact)) {
      showModalMessage(form, 'Contact number must be 7-20 digits and may include +,- or spaces', 'danger');
      form.querySelector('[name="contact_number"]').focus();
      return false;
    }

    const addr = form.querySelector('[name="store_address"]').value.trim();
    if (addr.length > 250) {
      showModalMessage(form, 'Store address is too long (max 250 chars)', 'danger');
      form.querySelector('[name="store_address"]').focus();
      return false;
    }

    const noteVal = form.querySelector('[name="note"]').value.trim();
    if (noteVal.length > 250) {
      showModalMessage(form, 'Note text is too long (max 250 chars)', 'danger');
      form.querySelector('[name="note"]').focus();
      return false;
    }

    return true;
  }

  // ✅ JS Validation for Profile Form - Only username editable now
  function validateProfileForm(form) {
    const username = form.querySelector('[name="username"]').value.trim();
    if (!username) {
      showModalMessage(form, 'Full name is required', 'danger');
      form.querySelector('[name="username"]').focus();
      return false;
    }
    return true;
  }

  // ✅ Update store display after successful update
  async function updateStoreDisplay() {
    try {
      const res = await fetch('/modules/settings/get_store_data.php');
      const data = await res.json();
      
      if (data.success) {
        // Update store name
        const storeNameEl = document.querySelector('[data-store-name]');
        if (storeNameEl) storeNameEl.textContent = data.store_name;

        // Update email
        const emailEl = document.querySelector('[data-store-email]');
        if (emailEl) emailEl.textContent = data.store_email;

        // Update admin contact (personal contact from users table)
        const adminContactEl = document.querySelector('[data-admin-contact]');
        if (adminContactEl) adminContactEl.textContent = data.admin_contact || '';

        // Update store contact (contact saved in stores table, used for invoice printing)
        const storeContactEl = document.querySelector('[data-store-contact]');
        if (storeContactEl) storeContactEl.textContent = data.contact_number || '';

        // Update GSTIN
        const gstinEl = document.querySelector('[data-gstin]');
        if (gstinEl) {
          if (data.gstin) {
            gstinEl.innerHTML = data.gstin;
          } else {
            gstinEl.innerHTML = '<span class="text-muted">-- Not entered --</span>';
          }
        }

        // Also update modal form values (set all instances, both modals)
        document.querySelectorAll('[name="store_name"]').forEach(el => el.value = data.store_name || '');
        document.querySelectorAll('[name="gstin"]').forEach(el => el.value = data.gstin || '');
        document.querySelectorAll('[name="contact_number"]').forEach(el => el.value = data.contact_number || '');
        const addressElSpan = document.querySelector('[data-store-address]');
        if (addressElSpan) addressElSpan.textContent = data.store_address || '';
        const noteElSpan = document.querySelector('[data-store-note]');
        if (noteElSpan) noteElSpan.textContent = ('note' in data) ? data.note : (data.notice || '');
        document.querySelectorAll('[name="store_address"]').forEach(el => el.value = data.store_address || '');
        document.querySelectorAll('[name="note"]').forEach(el => el.value = ('note' in data) ? data.note : (data.notice || ''));
        // Update billing field toggles (checkboxes)
        try {
          const bf = data.billing_fields && data.billing_fields !== '{}' ? (typeof data.billing_fields === 'string' ? JSON.parse(data.billing_fields) : data.billing_fields) : {};
          // keep globally accessible for toggle handlers
          window.currentBillingFields = bf;
          // update the print store email display checkbox in the store info card
          const printEmailDisplay = document.getElementById('printStoreEmailToggle');
          if (printEmailDisplay) {
            printEmailDisplay.checked = !!bf.print_store_email;
            printEmailDisplay.disabled = !data.store_email;
          }
          Object.keys(bf).forEach(k => {
            const el = document.querySelector('[name="fields['+k+']"]');
            if (el) el.checked = !!bf[k];
          });
          // ensure the print_store_email toggle exists and is false if not set
          const printEmailEl = document.querySelector('[name="fields[print_store_email]"]');
          if (printEmailEl && !('print_store_email' in bf)) printEmailEl.checked = false;
        } catch (e) {
          console.error('Error parsing billing_fields in updateStoreDisplay', e);
        }
      }
    } catch (err) {
      console.error('Error updating store display:', err);
    }
  }

  // ✅ Update profile display after successful update
  async function updateProfileDisplay() {
    try {
      const res = await fetch('/modules/settings/get_profile_data.php');
      const data = await res.json();
      
      if (data.success) {
        const userNameEl = document.querySelector('[data-user-name]');
        if (userNameEl) userNameEl.textContent = data.username;
        // Update admin contact display only if element exists
        const adminContactEl = document.querySelector('[data-admin-contact]');
        if (adminContactEl) adminContactEl.textContent = data.personal_contact_number || '';
        // Update modal form value only if input exists
        const usernameInput = document.querySelector('[name="username"]');
        if (usernameInput) usernameInput.value = data.username;
        const personalContactInput = document.querySelector('[name="personal_contact_number"]');
        if (personalContactInput) personalContactInput.value = data.personal_contact_number || '';
      }
    } catch (err) {
      console.error('Error updating profile display:', err);
    }
  }

  async function handleForm(formId, url, updateCallback = null, validator = null) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async e => {
      e.preventDefault();

      // Run custom validator if provided
      if (validator && !validator(form)) {
        return;
      }

      const fd = new FormData(form);

      try {
        const res = await fetch(`/modules/settings/${url}`, {
          method: 'POST',
          body: fd,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include'
        });

        const text = await res.text();
        console.log(`🔍 Raw response from ${url}:`, text);

        if (text.trim().startsWith('<')) {
          showModalMessage(form, 'Session expired. Please log in again.', 'danger');
          return;
        }

        const data = JSON.parse(text);
        const type = (data.success || data.status === 'success') ? 'success' : 'danger';
        let message = data.msg || data.message || 'Something went wrong.';
        if (data.warnings && Array.isArray(data.warnings) && data.warnings.length) {
          message += '<br>' + data.warnings.join('<br>');
        }
        showModalMessage(form, message, type);

        // On success → hide modal + call update callback (NO reload)
        if (type === 'success') {
          const modalEl = bootstrap.Modal.getInstance(form.closest('.modal'));
          if (modalEl) setTimeout(() => modalEl.hide(), 800);
          
          // Call callback to update display
          if (updateCallback) {
            setTimeout(updateCallback, 600);
          }
        }

      } catch (err) {
        console.error(`❌ Error submitting ${formId}:`, err);
        showModalMessage(form, 'Network or server error. Please try again.', 'danger');
      }
    });
  }

  /* ============================================================
     ✅ ATTACH ALL FORMS WITH VALIDATORS & CALLBACKS
  ============================================================ */
  handleForm('profileForm', 'update_profile.php', updateProfileDisplay, validateProfileForm);
  handleForm('storeInfoForm', 'update_store.php', updateStoreDisplay, validateStoreInfoForm);
  handleForm('invoiceForm', 'update_store.php', updateStoreDisplay, validateInvoiceForm);
  handleForm('billingFieldsForm', 'update_billing_fields.php');

  /* ============================================================
     🔐 CHANGE PASSWORD LOGIC
  ============================================================ */
  const verifyBtn = document.getElementById('verifyBtn');
  const updateBtn = document.getElementById('updateBtn');
  const verifyStep = document.getElementById('verifyStep');
  const updateStep = document.getElementById('updateStep');
  const passwordForm = document.getElementById('passwordForm');

  if (verifyBtn && updateBtn && passwordForm) {
    verifyBtn.addEventListener('click', async () => {
      const currentPassword = document.querySelector('[name="current_password"]').value.trim();

      if (!currentPassword) {
        showModalMessage(passwordForm, 'Current password is required', 'danger');
        return;
      }

      const fd = new FormData();
      fd.append('stage', 'verify');
      fd.append('current_password', currentPassword);

      try {
        const res = await fetch('/modules/settings/update_password.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        showModalMessage(passwordForm, data.msg, data.success ? 'success' : 'danger');

        if (data.success) {
          verifyStep.style.display = 'none';
          updateStep.style.display = 'block';
        }
      } catch {
        showModalMessage(passwordForm, 'Server error while verifying password.', 'danger');
      }
    });

    updateBtn.addEventListener('click', async () => {
      const newPassword = document.querySelector('[name="new_password"]').value.trim();
      const confirmPassword = document.querySelector('[name="confirm_password"]').value.trim();

      if (!newPassword) {
        showModalMessage(passwordForm, 'New password is required', 'danger');
        return;
      }

      if (!confirmPassword) {
        showModalMessage(passwordForm, 'Confirm password is required', 'danger');
        return;
      }

      if (newPassword !== confirmPassword) {
        showModalMessage(passwordForm, 'Passwords do not match', 'danger');
        return;
      }

      if (newPassword.length < 6) {
        showModalMessage(passwordForm, 'Password must be at least 6 characters', 'danger');
        return;
      }

      const fd = new FormData();
      fd.append('stage', 'update');
      fd.append('new_password', newPassword);
      fd.append('confirm_password', confirmPassword);

      try {
        const res = await fetch('/modules/settings/update_password.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        showModalMessage(passwordForm, data.msg, data.success ? 'success' : 'danger');

        if (data.success) {
          const modalEl = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
          if (modalEl) setTimeout(() => modalEl.hide(), 800);
          // Password changed - reload to be safe
          setTimeout(() => location.reload(), 1000);
        }
      } catch {
        showModalMessage(passwordForm, 'Error updating password.', 'danger');
      }
    });
  }

  // Enable Bootstrap tooltips
  document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    // Update the UI with current store & profile data
    updateStoreDisplay();
    updateProfileDisplay();
  });

  // Print store email toggle handler (visible in Store Info card)
  document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('printStoreEmailToggle');
    if (!toggle) return;
    toggle.addEventListener('change', async function() {
      const checked = !!toggle.checked;
      // ensure we have currentBillingFields (fallback: fetch existing from server)
      if (!window.currentBillingFields || Object.keys(window.currentBillingFields).length === 0) {
        try {
          const r = await fetch('/modules/settings/get_store_data.php', { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const d = await r.json();
          if (d && d.billing_fields) {
            window.currentBillingFields = (typeof d.billing_fields === 'string') ? JSON.parse(d.billing_fields) : d.billing_fields;
          } else {
            window.currentBillingFields = {};
          }
        } catch (err) {
          window.currentBillingFields = {};
        }
      }
      window.currentBillingFields.print_store_email = checked ? true : false;

      // Build FormData for update_billing_fields.php (we must pass all keys to avoid overwriting)
      const fd = new FormData();
      Object.keys(window.currentBillingFields).forEach(k => {
        // convert boolean to '1'/'0' or keep original values
        const val = window.currentBillingFields[k] ? '1' : '0';
        fd.append('fields['+k+']', val);
      });

      // show small saving indicator
      const label = document.querySelector('label[for="printStoreEmailToggle"]');
      const prevText = label ? label.textContent : '';
      if (label) label.textContent = 'Saving...';
      try {
        const res = await fetch('/modules/settings/update_billing_fields.php', { method: 'POST', body: fd, credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (!data.success) {
          if (label) label.textContent = prevText;
          alert(data.msg || 'Failed to update setting');
          // revert toggle to previous state
          toggle.checked = !checked;
          if (window.currentBillingFields) window.currentBillingFields.print_store_email = !checked;
        } else {
          if (label) label.textContent = prevText;
          // refresh UI
          updateStoreDisplay();
        }
      } catch (err) {
        if (label) label.textContent = prevText;
        console.error('Failed to save print_store_email toggle', err);
        alert('Network or server error while saving setting');
        toggle.checked = !checked;
        if (window.currentBillingFields) window.currentBillingFields.print_store_email = !checked;
      }
    });
  });

  // Copy Store Code to clipboard with tick feedback
  function copyStoreCode() {
    const code = document.getElementById('storeCode').textContent.trim();
    const icon = document.getElementById('copyIcon');

    navigator.clipboard.writeText(code).then(() => {
      icon.classList.remove('bi-clipboard', 'text-primary');
      icon.classList.add('bi-check-circle-fill', 'text-success');

      const toast = document.createElement('div');
      toast.textContent = 'Store code copied!';
      toast.style.position = 'fixed';
      toast.style.bottom = '20px';
      toast.style.right = '20px';
      toast.style.backgroundColor = '#198754';
      toast.style.color = 'white';
      toast.style.padding = '10px 16px';
      toast.style.borderRadius = '8px';
      toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
      toast.style.fontSize = '14px';
      toast.style.zIndex = '9999';
      document.body.appendChild(toast);

      setTimeout(() => toast.remove(), 1500);
      setTimeout(() => {
        icon.classList.remove('bi-check-circle-fill', 'text-success');
        icon.classList.add('bi-clipboard', 'text-primary');
      }, 1000);
    }).catch(() => {
      showModalMessage(document.body, 'Failed to copy store code.', 'danger');
    });
  }
  </script>

</body>

</html>



<!-- its not working fix it ans add contact-number seen only in personal andremove contact number in store section Store information section -->