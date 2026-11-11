<?php
declare(strict_types=1);

/**
 * หน้าผู้ดูแล: ซ่อน/แสดงกระทงที่ไม่เหมาะสม
 * ตั้งรหัสผ่านที่ config/admin.php (ดู config/admin.sample.php) หรือ environment variable KRATHONG_ADMIN_HASH
 * ถ้ายังไม่ตั้งรหัสผ่าน หน้านี้จะถูกปิดใช้งาน
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
startSecureSession();

const ADMIN_PER_PAGE = 50;

function adminPasswordHash(): string
{
    $env = (string) getenv('KRATHONG_ADMIN_HASH');
    if ($env !== '') {
        return $env;
    }
    $file = __DIR__ . '/../config/admin.php';
    if (is_file($file)) {
        $config = require $file;
        return is_array($config) ? (string) ($config['password_hash'] ?? '') : '';
    }
    return '';
}

$hash = adminPasswordHash();
$message = '';
$pdo = null;

try {
    $pdo = getDatabase();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $message = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['admin_csrf'];
$csrfOk = static fn (): bool => isset($_POST['csrf']) && hash_equals($csrf, (string) $_POST['csrf']);

if ($hash !== '' && $pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        if (!$csrfOk()) {
            $message = 'เซสชันหมดอายุ กรุณาลองใหม่';
        } elseif (!rateLimitAllow($pdo, 'admin_login', 5, 600)) {
            $message = 'พยายามเข้าสู่ระบบบ่อยเกินไป กรุณารอ 10 นาที';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            redirect('index.php');
        } else {
            $message = 'รหัสผ่านไม่ถูกต้อง';
        }
    } elseif (!empty($_SESSION['is_admin']) && $csrfOk()) {
        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            redirect('index.php');
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && in_array($action, ['hide', 'show'], true)) {
            $pdo->prepare('UPDATE krathongs SET is_hidden = :h WHERE id = :id')
                ->execute([':h' => $action === 'hide' ? 1 : 0, ':id' => $id]);
        }
        redirect('index.php?page=' . max(1, (int) ($_POST['page'] ?? 1)));
    }
}

$isAdmin = $hash !== '' && !empty($_SESSION['is_admin']);
$rows = [];
$total = 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$pages = 1;
if ($isAdmin && $pdo instanceof PDO) {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM krathongs')->fetchColumn();
    $pages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
    $page = min($page, $pages);
    $statement = $pdo->prepare('SELECT id, nickname, prayer, created_at, is_hidden FROM krathongs ORDER BY id DESC LIMIT :l OFFSET :o');
    $statement->bindValue(':l', ADMIN_PER_PAGE, PDO::PARAM_INT);
    $statement->bindValue(':o', ($page - 1) * ADMIN_PER_PAGE, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();
}

renderPageTop('ผู้ดูแลระบบ', 'create-page', '', ['path' => 'admin/']);
?>
    <main class="create-shell">
        <section class="legal-panel admin-panel">
            <h1>ผู้ดูแลระบบ</h1>
            <?php if ($message !== ''): ?><div class="form-alert" role="alert"><strong><?= e($message) ?></strong></div><?php endif; ?>

            <?php if ($hash === ''): ?>
                <p>ยังไม่ได้ตั้งรหัสผ่านผู้ดูแล หน้านี้จึงถูกปิดใช้งาน ดูวิธีตั้งค่าที่ <code>config/admin.sample.php</code></p>
            <?php elseif (!$isAdmin): ?>
                <form method="post" action="index.php" class="admin-login">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="login">
                    <label for="adminPassword">รหัสผ่าน</label>
                    <input type="password" id="adminPassword" name="password" required autocomplete="current-password">
                    <button class="button button-primary" type="submit">เข้าสู่ระบบ</button>
                </form>
            <?php else: ?>
                <form method="post" action="index.php" class="admin-bar">
                    <span>กระทงทั้งหมด <?= number_format($total) ?> ใบ</span>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <button class="button button-ghost" type="submit" name="action" value="logout">ออกจากระบบ</button>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>#</th><th>ชื่อเล่น</th><th>คำอธิษฐาน</th><th>เวลา (UTC)</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr class="<?= $row['is_hidden'] ? 'is-hidden' : '' ?>">
                                <td><a href="../pages/view.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener"><?= (int) $row['id'] ?></a></td>
                                <td><?= e((string) $row['nickname']) ?></td>
                                <td><?= e(mb_strimwidth((string) $row['prayer'], 0, 160, '…', 'UTF-8')) ?></td>
                                <td><?= e((string) $row['created_at']) ?></td>
                                <td>
                                    <form method="post" action="index.php">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="page" value="<?= $page ?>">
                                        <button class="button button-ghost" type="submit" name="action" value="<?= $row['is_hidden'] ? 'show' : 'hide' ?>"><?= $row['is_hidden'] ? 'แสดงอีกครั้ง' : 'ซ่อน' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                    <nav class="gallery-pager" aria-label="เปลี่ยนหน้า">
                        <?php if ($page > 1): ?><a class="button button-ghost" href="index.php?page=<?= $page - 1 ?>">← ก่อนหน้า</a><?php endif; ?>
                        <span>หน้า <?= $page ?> / <?= $pages ?></span>
                        <?php if ($page < $pages): ?><a class="button button-ghost" href="index.php?page=<?= $page + 1 ?>">ถัดไป →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
<?php renderPageBottom(); ?>
