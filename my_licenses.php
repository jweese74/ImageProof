<?php

/**
 * My Licences – CRUD dashboard
 * • Create / update / delete licence text blobs
 * • Mark one licence as default
 * --------------------------------------------------------------
 */
require_once 'auth.php';
require_login();
require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------
// Markdown helper (Parsedown - tiny, MIT-licensed)
//   • composer  :  composer require erusev/parsedown
//   • manual    :  download Parsedown.php into the project root
// ---------------------------------------------------------------
require_once __DIR__ . '/vendor/parsedown/Parsedown.php';
// or:  require_once __DIR__ . '/Parsedown.php';

$md = new Parsedown();
$md->setSafeMode(true);          // strips raw HTML → XSS protection

$user       = current_user();
$userId     = $user['user_id'];
$errors     = [];
$messages   = [];

/* ------------------------------------------------------------------
   Handle POST actions
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action = $_POST['action'] ?? '';

    /* ---- add / update ------------------------------------------ */
    if ($action === 'save') {
        $licId  = $_POST['lic_id']  ?? '';
        $name   = trim($_POST['name'] ?? '');
        $text   = trim($_POST['text_blob'] ?? '');
        $def    = isset($_POST['is_default']) ? 1 : 0;

        if ($name === '' || $text === '') {
            $errors[] = 'Name and text cannot be empty.';
        } else {
            if ($def) {
                // clear others
                $pdo->prepare(
                    'UPDATE licenses SET is_default = 0 WHERE user_id = ?'
                )->execute([$userId]);
            }
            if ($licId) {      // update
                $pdo->prepare(
                    'UPDATE licenses SET name=?,text_blob=?,is_default=? WHERE license_id=? AND user_id=?'
                )->execute([$name, $text, $def, $licId, $userId]);
                $messages[] = 'Licence updated.';
            } else {           // insert
                $pdo->prepare(
                    'INSERT INTO licenses (license_id,user_id,name,text_blob,is_default)
                     VALUES (UUID(),?,?,?,?)'
                )->execute([$userId, $name, $text, $def]);
                $messages[] = 'Licence saved.';
            }
        }

        /* ---- edit --------------------------------------------------- */
    } elseif ($action === 'edit') {
        // handled purely client-side (prefill form)

        /* ---- delete ------------------------------------------------- */
    } elseif ($action === 'delete' && !empty($_POST['lic_id'])) {
        $pdo->prepare(
            'DELETE FROM licenses WHERE license_id = ? AND user_id = ?'
        )->execute([$_POST['lic_id'], $userId]);
        $messages[] = 'Licence deleted.';
    }
}

/* ------------------------------------------------------------------
   Fetch licences for display
------------------------------------------------------------------ */
$licenses = $pdo->prepare(
    'SELECT license_id,name,text_blob,is_default
     FROM licenses WHERE user_id = ? ORDER BY created_at DESC'
);
$licenses->execute([$userId]);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>My Licences</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px auto;
            max-width: 720px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem
        }

        th,
        td {
            padding: .5rem;
            border-bottom: 1px solid #ccc;
            text-align: left;
            vertical-align: top
        }

        textarea {
            width: 100%;
            height: 120px
        }

        /* ------- new: pretty licence output -------- */
        .lic-text {
            white-space: pre-wrap;
            /* word-wrap within long lines */
            word-break: break-word;
            line-height: 1.4;
        }

        .msg {
            color: #27ae60
        }

        .err {
            color: #c0392b
        }
    </style>
    <script>
        function editLicence(id, name, text, def) {
            document.getElementById('lic_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('text_blob').value = text;
            document.getElementById('is_default').checked = def;
            document.getElementById('submitBtn').textContent = 'Update';
        }
    </script>
</head>

<body>

    <h1>My Licences</h1>
    <p><a href="index.php">← back to uploader</a></p>

    <?php foreach ($messages as $m) {
        echo "<p class='msg'>" . htmlspecialchars($m) . "</p>";
    }
    foreach ($errors   as $e) {
        echo "<p class='err'>" . htmlspecialchars($e) . "</p>";
    } ?>

    <!-- form -->
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="lic_id" id="lic_id">
        <label>Name<br><input name="name" id="name" required></label><br>
        <label>Licence text<br><textarea name="text_blob" id="text_blob" required></textarea></label><br>
        <label><input type="checkbox" name="is_default" id="is_default"> Make default</label><br>
        <button type="submit" id="submitBtn">Save</button>
    </form>

    <!-- list -->
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Default</th>
                <th>Text</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($licenses as $lic): ?>
                <tr>
                    <td><?= htmlspecialchars($lic['name']) ?></td>
                    <td><?= $lic['is_default'] ? '✔' : '' ?></td>
                    <td class="lic-text">
                        <?= $md->text($lic['text_blob']) ?>
                    </td>
                    <td>
                        <button onclick="editLicence(
          '<?= htmlspecialchars($lic['license_id']) ?>',
          '<?= htmlspecialchars(addslashes($lic['name'])) ?>',
          <?= json_encode($lic['text_blob']) ?>,
          <?= $lic['is_default'] ? 'true' : 'false' ?>
      );">Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this licence?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="lic_id" value="<?= htmlspecialchars($lic['license_id']) ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>