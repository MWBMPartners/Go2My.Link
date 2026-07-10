<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 🔑 Go2My.Link — API Key Management (Admin Dashboard)
 * ============================================================================
 *
 * Create, list, and revoke public API keys (/api/v1/*) for the caller's
 * organisation. All heavy lifting — key minting, hashing, listing, and
 * org-scoped revocation — is delegated to the already-tested backend in
 * web/_functions/api_auth.php (#38); this page only handles the form/CSRF/
 * authz plumbing and rendering.
 *
 * The plaintext key returned by g2ml_apiGenerateKey() is shown exactly ONCE,
 * immediately after creation, in a dedicated "copy it now" panel. It is held
 * only in a local PHP variable for the lifetime of this single request/
 * response — never written to the session, a cookie, a log, or the database
 * (only its SHA-256 hash is persisted, by the backend). Reloading this page
 * cannot bring it back.
 *
 * Authorisation matches the org/domains page exactly: the caller must belong
 * to a real organisation (not the shared "[default]" org) and must pass
 * canManageOrg() (org Admin or GlobalAdmin). This is a deliberate
 * least-privilege default for the first cut of this page — ordinary org
 * members cannot mint or revoke API keys yet. If the product wants to let
 * regular members create their own read-only keys later, that would need a
 * separate, narrower authz path (and probably per-member key ownership
 * checks in addition to org scoping).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.1.0
 * @since      v1.1.0 — Phase 7 (#40)
 *
 * 📖 References:
 *     - Backend:       web/_functions/api_auth.php (#38)
 *     - Schema:        web/_sql/schema/031_api.sql
 *     - Modelled on:   web/Go2My.Link/_admin/public_html/pages/org/domains/index.php
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('apikeys.title');
} else {
    $pageTitle = 'API Keys';
}
if (function_exists('__')) {
    $pageDesc = __('apikeys.description');
} else {
    $pageDesc = 'Create and manage API keys for your organisation.';
}

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

if ($orgHandle === '[default]' || !canManageOrg($orgHandle))
{
    echo '<div class="container py-5"><div class="alert alert-danger">';
    echo '<i class="fas fa-lock" aria-hidden="true"></i> ';
    if (function_exists('__')) {
        echo g2ml_sanitiseOutput(__('apikeys.error_forbidden'));
    } else {
        echo 'You do not have permission to manage API keys.';
    }
    echo '</div></div>';
    return;
}

$actionSuccess       = '';
$actionError         = '';
$createdKeyPlaintext = null;
$createdKeyPrefix    = null;
$createdKeyName      = null;

// ============================================================================
// 🏷️ Scope catalogue for the "create key" checkboxes
// ============================================================================
// The values come from g2ml_apiValidScopesList() (the single source of truth
// shared with the input-validation helper below) — only the human-readable
// labels are local to this page.
// ============================================================================

$scopeLabels = [
    'urls:read'      => 'View short links',
    'urls:write'     => 'Create & edit short links',
    'urls:delete'    => 'Delete short links',
    'analytics:read' => 'View analytics',
    'domains:read'   => 'View custom domains',
    'domains:write'  => 'Manage custom domains',
    'org:read'       => 'View organisation details',
    'account:read'   => 'View account details',
    'qr:link'        => 'Link dynamic QR codes (CueRCode)',
];

$availableScopes = g2ml_apiValidScopesList();

// ============================================================================
// Handle POST actions
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken  = $_POST['_csrf_token'] ?? '';
    $actionType = $_POST['action_type'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'api_keys_form'))
    {
        $actionError = 'Session expired. Please try again.';
    }
    else
    {
        switch ($actionType)
        {
            case 'create_key':

                $keyName = trim(g2ml_sanitiseInput($_POST['key_name'] ?? ''));

                $requestedScopes = $_POST['scopes'] ?? [];

                if (!is_array($requestedScopes))
                {
                    $requestedScopes = [];
                }

                $validScopes = g2ml_apiValidateScopesInput($requestedScopes);

                $expiresAtRaw   = trim(g2ml_sanitiseInput($_POST['expires_at'] ?? ''));
                $expiresAtValue = null;
                $expiryError    = '';

                if ($expiresAtRaw !== '')
                {
                    $expiryDateTime = DateTime::createFromFormat('Y-m-d', $expiresAtRaw);

                    if ($expiryDateTime === false || $expiryDateTime->format('Y-m-d') !== $expiresAtRaw)
                    {
                        $expiryError = 'Invalid expiry date.';
                    }
                    elseif ($expiryDateTime->format('Y-m-d') < date('Y-m-d'))
                    {
                        $expiryError = 'Expiry date must be in the future.';
                    }
                    else
                    {
                        $expiresAtValue = $expiryDateTime->format('Y-m-d') . ' 23:59:59';
                    }
                }

                if ($keyName === '')
                {
                    $actionError = 'Please enter a name for this key.';
                }
                elseif (mb_strlen($keyName) > 255)
                {
                    $actionError = 'Key name must be 255 characters or fewer.';
                }
                elseif (count($validScopes) === 0)
                {
                    $actionError = 'Please select at least one permission.';
                }
                elseif ($expiryError !== '')
                {
                    $actionError = $expiryError;
                }
                else
                {
                    $created = g2ml_apiGenerateKey(
                        $currentUser['userUID'],
                        $orgHandle,
                        $keyName,
                        $validScopes,
                        $expiresAtValue
                    );

                    if ($created['success'])
                    {
                        $createdKeyPlaintext = $created['plaintextKey'];
                        $createdKeyPrefix    = $created['apiKeyPrefix'];
                        $createdKeyName      = $keyName;
                        $actionSuccess       = 'API key created.';
                    }
                    else
                    {
                        $actionError = $created['error'] ?? 'Failed to create API key.';
                    }
                }
                break;

            case 'revoke_key':

                $apiKeyUID = (int) ($_POST['api_key_uid'] ?? 0);

                if ($apiKeyUID <= 0)
                {
                    $actionError = 'Invalid API key.';
                }
                else
                {
                    $revoked = g2ml_apiRevokeKey($apiKeyUID, $orgHandle);

                    if ($revoked)
                    {
                        $actionSuccess = 'API key revoked.';
                    }
                    else
                    {
                        $actionError = 'That key could not be found, or it does not belong to your organisation.';
                    }
                }
                break;
        }
    }
}

// Load data
$apiKeys = g2ml_apiListKeys($orgHandle);
?>

<section class="py-4" aria-labelledby="apikeys-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/org">Organisation</a></li>
                <li class="breadcrumb-item active" aria-current="page">API Keys</li>
            </ol>
        </nav>

        <h1 id="apikeys-heading" class="h2 mb-4">
            <i class="fas fa-key" aria-hidden="true"></i> API Keys
        </h1>

        <?php if ($actionSuccess !== '' && $createdKeyPlaintext === null) { ?>
        <div class="alert alert-success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
        </div>
        <?php } ?>

        <?php if ($actionError !== '') { ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionError); ?>
        </div>
        <?php } ?>

        <?php if ($createdKeyPlaintext !== null) { ?>
        <!-- ================================================================ -->
        <!-- One-time plaintext key panel — never shown again after this      -->
        <!-- ================================================================ -->
        <div class="card shadow-sm border-success mb-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-3 text-success">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    API Key Created: <?php echo g2ml_sanitiseOutput($createdKeyName); ?>
                </h2>

                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <strong>Copy this key now.</strong>
                    For your security, we only store a one-way hash of it — this is the
                    only time the full key will ever be displayed. If you lose it, you
                    will need to revoke this key and create a new one.
                </div>

                <label for="new-api-key" class="form-label">
                    Your new API key
                </label>
                <div class="input-group input-group-lg mb-2">
                    <input type="text" class="form-control font-monospace" id="new-api-key"
                           value="<?php echo g2ml_sanitiseOutput($createdKeyPlaintext); ?>" readonly
                           aria-label="Newly created API key">
                    <button class="btn btn-primary" type="button" id="copy-key-btn"
                            aria-label="Copy API key to clipboard"
                            onclick="navigator.clipboard.writeText(document.getElementById('new-api-key').value).then(function(){var b=document.getElementById('copy-key-btn');b.textContent='✓ Copied!';var s=document.getElementById('global-status');if(s){s.textContent='API key copied to clipboard';}}).catch(function(){var s=document.getElementById('global-status');if(s){s.textContent='Failed to copy. Please select and copy manually.';}})">
                        <i class="fas fa-copy" aria-hidden="true"></i> Copy
                    </button>
                </div>
                <p class="text-body-secondary small mb-0">
                    Key prefix: <code><?php echo g2ml_sanitiseOutput($createdKeyPrefix); ?></code>
                    — use this prefix to identify the key in the list below once you navigate away.
                </p>
            </div>
        </div>
        <?php } ?>

        <!-- ================================================================ -->
        <!-- API Keys Table                                                    -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Your API Keys (<?php echo count($apiKeys); ?>)</h2>
            </div>

            <?php if (empty($apiKeys)) { ?>
            <div class="card-body text-center text-body-secondary py-4">
                <i class="fas fa-key fa-2x mb-2" aria-hidden="true"></i>
                <p class="mb-0">No API keys created yet.</p>
            </div>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Key Prefix</th>
                            <th scope="col">Permissions</th>
                            <th scope="col">Created</th>
                            <th scope="col">Last Used</th>
                            <th scope="col">Expires</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiKeys as $keyRow) { ?>
                        <?php
                        $isRevoked = ((int) $keyRow['isActive']) === 0;
                        $isExpired = false;

                        if ($keyRow['expiresAt'] !== null)
                        {
                            $expiryTimestamp = strtotime((string) $keyRow['expiresAt']);

                            if ($expiryTimestamp !== false && $expiryTimestamp < time())
                            {
                                $isExpired = true;
                            }
                        }

                        if ($isRevoked)
                        {
                            $statusLabel = 'Revoked';
                            $statusBadge = 'bg-secondary';
                        }
                        elseif ($isExpired)
                        {
                            $statusLabel = 'Expired';
                            $statusBadge = 'bg-warning text-dark';
                        }
                        else
                        {
                            $statusLabel = 'Active';
                            $statusBadge = 'bg-success';
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo g2ml_sanitiseOutput($keyRow['keyName']); ?></strong></td>
                            <td><code><?php echo g2ml_sanitiseOutput($keyRow['apiKeyPrefix']); ?>&hellip;</code></td>
                            <td>
                                <?php
                                $rowScopes = $keyRow['permissions'];

                                if (!is_array($rowScopes))
                                {
                                    $rowScopes = [];
                                }

                                foreach ($rowScopes as $rowScope)
                                {
                                    if (!is_string($rowScope))
                                    {
                                        continue;
                                    }
                                    ?>
                                    <span class="badge bg-info text-dark me-1 mb-1"><?php echo g2ml_sanitiseOutput($rowScope); ?></span>
                                    <?php
                                }
                                ?>
                            </td>
                            <td>
                                <time datetime="<?php echo g2ml_sanitiseOutput((string) $keyRow['createdAt']); ?>">
                                    <?php echo date('j M Y', strtotime((string) $keyRow['createdAt'])); ?>
                                </time>
                            </td>
                            <td>
                                <?php if ($keyRow['lastUsedAt'] !== null) { ?>
                                <time datetime="<?php echo g2ml_sanitiseOutput((string) $keyRow['lastUsedAt']); ?>">
                                    <?php echo date('j M Y, H:i', strtotime((string) $keyRow['lastUsedAt'])); ?>
                                </time>
                                <?php } else { ?>
                                <span class="text-body-secondary">Never</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($keyRow['expiresAt'] !== null) { ?>
                                <time datetime="<?php echo g2ml_sanitiseOutput((string) $keyRow['expiresAt']); ?>">
                                    <?php echo date('j M Y', strtotime((string) $keyRow['expiresAt'])); ?>
                                </time>
                                <?php } else { ?>
                                <span class="text-body-secondary">Never</span>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $statusBadge; ?>">
                                    <?php echo g2ml_sanitiseOutput($statusLabel); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$isRevoked) { ?>
                                <form action="/api-keys" method="POST" class="d-inline"
                                      onsubmit="return confirm('Revoke this API key? Anything using it will stop working immediately. This cannot be undone.');">
                                    <?php echo g2ml_csrfField('api_keys_form'); ?>
                                    <input type="hidden" name="action_type" value="revoke_key">
                                    <input type="hidden" name="api_key_uid" value="<?php echo (int) $keyRow['apiKeyUID']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                            title="Revoke key" aria-label="Revoke <?php echo g2ml_sanitiseOutput($keyRow['keyName']); ?>">
                                        <i class="fas fa-ban" aria-hidden="true"></i> Revoke
                                    </button>
                                </form>
                                <?php } else { ?>
                                <span class="text-body-secondary small">&mdash;</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>

        <!-- ================================================================ -->
        <!-- Create API Key Form                                               -->
        <!-- ================================================================ -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i> Create API Key
                </h2>
            </div>
            <div class="card-body">
                <form action="/api-keys" method="POST" novalidate>
                    <?php echo g2ml_csrfField('api_keys_form'); ?>
                    <input type="hidden" name="action_type" value="create_key">

                    <?php
                    echo formField([
                        'id'       => 'key-name',
                        'name'     => 'key_name',
                        'label'    => 'Key Name',
                        'type'     => 'text',
                        'required' => true,
                        'value'    => '',
                        'helpText' => 'A label to help you recognise this key later, e.g. "CI pipeline" or "Zapier integration".',
                    ]);
                    ?>

                    <fieldset class="mb-3">
                        <legend class="form-label h6">
                            Permissions <span class="text-danger" aria-hidden="true">*</span>
                            <?php if (function_exists('srOnly')) { echo srOnly('(required, select at least one)'); } ?>
                        </legend>
                        <div class="row">
                            <?php foreach ($availableScopes as $scopeValue) { ?>
                            <?php
                            if (isset($scopeLabels[$scopeValue]))
                            {
                                $scopeLabel = $scopeLabels[$scopeValue];
                            }
                            else
                            {
                                $scopeLabel = $scopeValue;
                            }

                            $scopeInputID = 'scope-' . preg_replace('/[^a-zA-Z0-9]/', '-', $scopeValue);
                            ?>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox"
                                           id="<?php echo g2ml_sanitiseOutput($scopeInputID); ?>"
                                           name="scopes[]"
                                           value="<?php echo g2ml_sanitiseOutput($scopeValue); ?>">
                                    <label class="form-check-label" for="<?php echo g2ml_sanitiseOutput($scopeInputID); ?>">
                                        <code><?php echo g2ml_sanitiseOutput($scopeValue); ?></code>
                                        &mdash; <?php echo g2ml_sanitiseOutput($scopeLabel); ?>
                                    </label>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </fieldset>

                    <div class="mb-3">
                        <label for="expires-at" class="form-label">Expiry Date (Optional)</label>
                        <input type="date" class="form-control" id="expires-at" name="expires_at"
                               aria-describedby="expires-at-help" style="max-width:250px;">
                        <div id="expires-at-help" class="form-text">
                            Leave blank for a key that never expires.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key" aria-hidden="true"></i> Create Key
                    </button>
                </form>
            </div>
        </div>

    </div>
</section>
