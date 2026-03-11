<?php
/**
 * UpgradedProxy — Smart Entry Point
 * - Not installed → shows install wizard (no redirect!)
 * - Installed     → serves React SPA
 */

define('API_PATH',  __DIR__ . '/api');
define('LOCK_FILE', API_PATH . '/storage/installed.lock');
define('ENV_FILE',  API_PATH . '/.env');

// ─── INSTALLED: Serve React SPA ───────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    $uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = ltrim(urldecode($uri), '/');

    // ─── SMART REDIRECT: Fix Verification Link (Missing /api prefix) ──────
    if ($path === 'auth/verify-email-link') {
        $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: /api/' . $path . $queryString);
        exit;
    }

    $file = __DIR__ . '/' . $path;
    // Serve static assets directly
    if ($path !== '' && is_file($file)) {
        $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = ['js'=>'application/javascript','css'=>'text/css','svg'=>'image/svg+xml',
                  'png'=>'image/png','ico'=>'image/x-icon','woff2'=>'font/woff2',
                  'woff'=>'font/woff','ttf'=>'font/ttf','webp'=>'image/webp',
                  'json'=>'application/json','webmanifest'=>'application/manifest+json'];
        if (isset($types[$ext])) header('Content-Type: ' . $types[$ext]);
        readfile($file); exit;
    }

    // All React routes → index.html
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

// ─── NOT INSTALLED: Run Installer ─────────────────────────────────────────
session_start();
if (!isset($_SESSION['wiz_step'])) $_SESSION['wiz_step'] = 1;
$step = $_SESSION['wiz_step'];
$err  = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'go2') { $_SESSION['wiz_step'] = 2; header('Location: /'); exit; }

    if ($action === 'test_db') {
        $h = trim($_POST['db_host'] ?? 'localhost');
        $P = trim($_POST['db_port'] ?? '3306');
        $n = trim($_POST['db_name'] ?? '');
        $u = trim($_POST['db_user'] ?? '');
        $p = trim($_POST['db_pass'] ?? '');
        if (!$n || !$u) { $err = 'Database name and username are required.'; }
        else {
            try {
                new PDO("mysql:host=$h;port=$P;dbname=$n", $u, $p,
                    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>5]);
                $_SESSION['db'] = ['host'=>$h,'port'=>$P,'name'=>$n,'user'=>$u,'pass'=>$p];
                $_SESSION['wiz_step'] = 3; $step = 3;
                $ok = 'Database connected successfully!';
            } catch (PDOException $e) { $err = 'Connection failed: ' . $e->getMessage(); }
        }
    }

    if ($action === 'save_cfg') {
        $_SESSION['cfg'] = [
            'app_name' => trim($_POST['app_name'] ?? 'UpgradedProxy'),
            'app_url'  => rtrim(trim($_POST['app_url'] ?? ''), '/'),
            'evomi'    => trim($_POST['evomi'] ?? ''),
            'sk'       => trim($_POST['sk'] ?? ''),
            'ss'       => trim($_POST['ss'] ?? ''),
            'swh'      => trim($_POST['swh'] ?? ''),
            'mh'       => trim($_POST['mh'] ?? ''),
            'mp'       => trim($_POST['mp'] ?? '587'),
            'mu'       => trim($_POST['mu'] ?? ''),
            'mpw'      => trim($_POST['mpw'] ?? ''),
            'mf'       => trim($_POST['mf'] ?? ''),
        ];
        $_SESSION['wiz_step'] = 4; $step = 4;
    }

    if ($action === 'save_admin') {
        $an = trim($_POST['an'] ?? ''); $ae = trim($_POST['ae'] ?? '');
        $ap = trim($_POST['ap'] ?? ''); $ap2 = trim($_POST['ap2'] ?? '');
        if (!$an||!$ae||!$ap) { $err='All fields required.'; }
        elseif ($ap!==$ap2)   { $err='Passwords do not match.'; }
        elseif (strlen($ap)<8){ $err='Password must be 8+ characters.'; }
        else { $_SESSION['adm']=['name'=>$an,'email'=>$ae,'pass'=>$ap]; $_SESSION['wiz_step']=5; $step=5; }
    }

    if ($action === 'install') {
        $db = $_SESSION['db'] ?? []; $cfg = $_SESSION['cfg'] ?? []; $adm = $_SESSION['adm'] ?? [];
        $logs = [];
        // Write .env
        $key = 'base64:' . base64_encode(random_bytes(32));
        $env = "APP_NAME={$cfg['app_name']}\nAPP_ENV=production\nAPP_KEY=$key\nAPP_DEBUG=false\nAPP_URL={$cfg['app_url']}/api\n\n"
             . "APP_LOCALE=en\nAPP_FALLBACK_LOCALE=en\nAPP_FAKER_LOCALE=en_US\nAPP_MAINTENANCE_DRIVER=file\nBCRYPT_ROUNDS=12\n\n"
             . "LOG_CHANNEL=stack\nLOG_STACK=single\nLOG_LEVEL=error\n\n"
             . "DB_CONNECTION=mysql\nDB_HOST={$db['host']}\nDB_PORT={$db['port']}\nDB_DATABASE={$db['name']}\nDB_USERNAME={$db['user']}\nDB_PASSWORD={$db['pass']}\n\n"
             . "SESSION_DRIVER=file\nSESSION_LIFETIME=120\nSESSION_ENCRYPT=false\nSESSION_PATH=/\nSESSION_DOMAIN=null\n\n"
             . "BROADCAST_CONNECTION=log\nFILESYSTEM_DISK=local\nQUEUE_CONNECTION=sync\nCACHE_STORE=file\n\n"
             . "MAIL_MAILER=smtp\nMAIL_HOST={$cfg['mh']}\nMAIL_PORT={$cfg['mp']}\nMAIL_USERNAME={$cfg['mu']}\nMAIL_PASSWORD={$cfg['mpw']}\nMAIL_FROM_ADDRESS=\"{$cfg['mf']}\"\nMAIL_FROM_NAME=\"\${APP_NAME}\"\n\n"
             . "EVOMI_API_KEY={$cfg['evomi']}\nSTRIPE_KEY={$cfg['sk']}\nSTRIPE_SECRET={$cfg['ss']}\nSTRIPE_WEBHOOK_SECRET={$cfg['swh']}\n";
        $logs[] = file_put_contents(ENV_FILE, $env) ? ['ok','✅ .env written'] : ['err','❌ .env write failed — check api/ permissions'];
        // Run artisan
        $php = PHP_BINARY; $bp = escapeshellarg(API_PATH);
        exec("cd $bp && $php artisan config:clear 2>&1"); $logs[] = ['ok','✅ Config cache cleared'];
        exec("cd $bp && $php artisan migrate --force 2>&1", $mo, $mc);
        $logs[] = [$mc===0 ? 'ok':'err', $mc===0 ? '✅ Migrations ran' : '❌ Migration error: '.implode(' ',array_slice($mo,-2))];
        exec("cd $bp && $php artisan db:seed --class=ProductSeeder --force 2>&1", $so, $sc);
        $logs[] = [$sc===0 ? 'ok':'warn', $sc===0 ? '✅ Products seeded' : '⚠️ Seeder: '.implode(' ',array_slice($so,-1))];
        // Create admin
        try {
            $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']}",
                           $db['user'], $db['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $hp = password_hash($adm['pass'], PASSWORD_BCRYPT, ['cost'=>12]);
            $rc = 'UP-' . strtoupper(substr(bin2hex(random_bytes(4)),0,8)); $now = date('Y-m-d H:i:s');
            $chk = $pdo->prepare('SELECT id FROM users WHERE email=?'); $chk->execute([$adm['email']]);
            if ($chk->fetchColumn()) {
                $pdo->prepare("UPDATE users SET role='admin',name=?,password=? WHERE email=?")->execute([$adm['name'],$hp,$adm['email']]);
                $logs[] = ['ok','✅ Existing user promoted to admin'];
            } else {
                $pdo->prepare("INSERT INTO users(name,email,password,role,referral_code,balance,created_at,updated_at)VALUES(?,?,?,'admin',?,0,?,?)")->execute([$adm['name'],$adm['email'],$hp,$rc,$now,$now]);
                $logs[] = ['ok','✅ Admin created: '.$adm['email']];
            }
        } catch(PDOException $e) { $logs[] = ['err','❌ Admin error: '.$e->getMessage()]; }
        // Cache & lock
        exec("cd $bp && $php artisan config:cache 2>&1");
        exec("cd $bp && $php artisan route:cache 2>&1");
        $logs[] = ['ok','✅ Config & routes cached'];
        $locked = file_put_contents(LOCK_FILE, date('Y-m-d H:i:s')."\nInstalled.\n");
        $logs[] = $locked ? ['ok','✅ Installation locked — wizard will not show again'] : ['warn','⚠️ Could not write lock file. Create api/storage/installed.lock manually.'];
        $_SESSION['wiz_logs'] = $logs; $_SESSION['wiz_step'] = 6; $step = 6;
    }
}
// Requirements
$reqs = [
    ['PHP >= 8.1', PHP_VERSION_ID>=80100, PHP_VERSION],
    ['PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql')?'Loaded':'❌ Missing'],
    ['OpenSSL', extension_loaded('openssl'), extension_loaded('openssl')?'Loaded':'❌ Missing'],
    ['storage/ Writable', is_writable(API_PATH.'/storage'), is_writable(API_PATH.'/storage')?'✓ Writable':'❌ Not Writable!'],
    ['api/.env Writable', is_writable(API_PATH)||is_writable(ENV_FILE), 'Check permissions'],
    ['exec() Available', function_exists('exec'), function_exists('exec')?'Available':'⚠️ Disabled'],
];
$allOk = true; foreach($reqs as $r){ if(!$r[1]&&strpos($r[0],'exec')===false) $allOk=false; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>UpgradedProxy — Install</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a0a14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;min-height:100vh;display:flex;flex-direction:column}
.grid{position:fixed;inset:0;background-image:linear-gradient(rgba(99,102,241,.04)1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.04)1px,transparent 1px);background-size:40px 40px;pointer-events:none}
.hdr{border-bottom:1px solid rgba(99,102,241,.2);padding:16px 36px;display:flex;align-items:center;gap:12px;background:rgba(10,10,20,.9);backdrop-filter:blur(12px)}
.logo{width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:17px}
.lt{font-size:17px;font-weight:700}.lt s{color:#6366f1;text-decoration:none}
.badge{margin-left:auto;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);color:#818cf8;padding:3px 12px;border-radius:20px;font-size:12px}
.wrap{max-width:680px;margin:0 auto;padding:32px 18px;flex:1;position:relative;z-index:1}
.steps{display:flex;align-items:center;margin-bottom:32px}
.si{display:flex;flex-direction:column;align-items:center;gap:5px;flex:1}
.sc{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid}
.done{background:#6366f1;border-color:#6366f1;color:#fff}.act{background:rgba(99,102,241,.2);border-color:#6366f1;color:#818cf8}.pen{background:transparent;border-color:rgba(255,255,255,.1);color:#4b5563}
.sl{font-size:10px;color:#6b7280;white-space:nowrap}.alb{color:#818cf8}
.line{flex:1;height:1px;background:rgba(255,255,255,.1);margin:0 5px;margin-bottom:20px}.line.d{background:#6366f1}
.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:28px}
.ct{font-size:21px;font-weight:700;margin-bottom:5px}.cs{color:#6b7280;font-size:14px;margin-bottom:22px}
.fg{margin-bottom:16px}.fr{display:grid;grid-template-columns:1fr 1fr;gap:13px}
label{display:block;font-size:12px;font-weight:500;color:#9ca3af;margin-bottom:5px}
input{width:100%;padding:10px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s}
input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}input::placeholder{color:#374151}
.hint{font-size:11px;color:#4b5563;margin-top:3px}
.btn{padding:11px 22px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;transition:all .2s}
.btn-p{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 4px 18px rgba(99,102,241,.3)}.btn-p:hover{transform:translateY(-1px)}
.btn-g{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 4px 18px rgba(16,185,129,.25)}.btn-g:hover{transform:translateY(-1px)}
.btn-s{background:rgba(255,255,255,.06);color:#9ca3af;border:1px solid rgba(255,255,255,.1)}
.full{width:100%;justify-content:center;padding:13px;font-size:15px}
.ae{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;gap:8px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.as{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;gap:8px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#6ee7b7}
.rt{width:100%;border-collapse:collapse}.rt td{padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px}.rt td:last-child{text-align:right}
.bok{padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(16,185,129,.15);color:#6ee7b7}
.ber{padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(239,68,68,.15);color:#fca5a5}
.bwn{padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(245,158,11,.15);color:#fcd34d}
.stt{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:#4b5563;margin:20px 0 10px}
.cb{background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.18);border-radius:12px;padding:16px;margin:18px 0}
.cr{display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04)}.cr:last-child{border:none}
.cl{color:#6b7280}.cv{color:#e2e8f0;font-family:monospace;font-size:12px}
.li{display:flex;align-items:baseline;gap:9px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:13px}
.di{font-size:56px;text-align:center;margin-bottom:14px}
.brow{display:flex;gap:11px;margin-top:18px}
foot{display:block;text-align:center;padding:18px;color:#374151;font-size:12px;border-top:1px solid rgba(255,255,255,.05)}
</style>
</head>
<body>
<div class="grid"></div>
<div class="hdr">
  <div class="logo">U</div>
  <div class="lt">Upgraded<s>Proxy</s></div>
  <div class="badge">✦ Installation Wizard</div>
</div>
<div class="wrap">
<?php if($step<=5): ?>
<div class="steps">
<?php $lbls=['Welcome','Database','App Config','Admin','Install'];
foreach($lbls as $i=>$l):$n=$i+1;$sc=$n<$step?'done':($n==$step?'act':'pen');$al=$n==$step?'alb':'';?>
<?php if($i>0):?><div class="line <?=$n<=$step?'d':''?>"></div><?php endif;?>
<div class="si"><div class="sc <?=$sc?>"><?=$sc==='done'?'✓':$n?></div><div class="sl <?=$al?>"><?=$l?></div></div>
<?php endforeach;?></div>
<?php endif;?>
<?php if($err):?><div class="ae">⚠️ <?=htmlspecialchars($err)?></div><?php endif;?>
<?php if($ok):?><div class="as">✓ <?=htmlspecialchars($ok)?></div><?php endif;?>

<?php if($step===1):?>
<div class="card">
<div class="ct">👋 Welcome to UpgradedProxy</div>
<div class="cs">Checking server requirements before installation.</div>
<table class="rt">
<?php foreach($reqs as $r):?>
<tr><td><?=$r[0]?></td><td><?=$r[2]?></td><td><span class="<?=$r[1]?'bok':'ber'?>"><?=$r[1]?'✓ Pass':'✗ Fail'?></span></td></tr>
<?php endforeach;?>
</table><br>
<?php if($allOk):?>
<div class="as">✓ All requirements met!</div>
<form method="POST"><input type="hidden" name="action" value="go2">
<button class="btn btn-p full" type="submit">Continue to Database Setup →</button></form>
<?php else:?><div class="ae">⚠️ Fix the failing requirements before continuing.</div><?php endif;?>
</div>

<?php elseif($step===2):?>
<div class="card">
<div class="ct">🗄️ Database Configuration</div>
<div class="cs">Enter your cPanel MySQL credentials. The database must already exist.</div>
<form method="POST"><input type="hidden" name="action" value="test_db">
<div class="fr">
  <div class="fg"><label>Host</label><input name="db_host" value="<?=htmlspecialchars($_SESSION['db']['host']??'localhost')?>" placeholder="localhost"></div>
  <div class="fg"><label>Port</label><input name="db_port" value="<?=htmlspecialchars($_SESSION['db']['port']??'3306')?>" placeholder="3306"></div>
</div>
<div class="fg"><label>Database Name</label><input name="db_name" value="<?=htmlspecialchars($_SESSION['db']['name']??'')?>" placeholder="user_proxydb" required><div class="hint">Create in cPanel → MySQL Databases first</div></div>
<div class="fr">
  <div class="fg"><label>Username</label><input name="db_user" value="<?=htmlspecialchars($_SESSION['db']['user']??'')?>" placeholder="user_dbuser" required></div>
  <div class="fg"><label>Password</label><input type="password" name="db_pass" placeholder="••••••••"></div>
</div>
<button class="btn btn-p full" type="submit">Test Connection & Continue →</button>
</form></div>

<?php elseif($step===3):?>
<div class="card">
<div class="ct">⚙️ App Configuration</div>
<div class="cs">Set your application URL and API keys.</div>
<form method="POST"><input type="hidden" name="action" value="save_cfg">
<div class="fr">
  <div class="fg"><label>App Name</label><input name="app_name" value="<?=htmlspecialchars($_SESSION['cfg']['app_name']??'UpgradedProxy')?>"></div>
  <div class="fg"><label>App URL (no trailing slash)</label><input name="app_url" type="url" value="<?=htmlspecialchars($_SESSION['cfg']['app_url']??'')?>" placeholder="https://yourdomain.com" required><div class="hint">e.g. https://devwithguru.site</div></div>
</div>
<div class="stt">🔑 API Keys</div>
<div class="fg"><label>Evomi Reseller API Key</label><input name="evomi" value="<?=htmlspecialchars($_SESSION['cfg']['evomi']??'')?>" placeholder="evomi_xxxx"></div>
<div class="fg"><label>Stripe Publishable Key</label><input name="sk" value="<?=htmlspecialchars($_SESSION['cfg']['sk']??'')?>" placeholder="pk_live_..."></div>
<div class="fr">
  <div class="fg"><label>Stripe Secret Key</label><input type="password" name="ss" placeholder="sk_live_..."></div>
  <div class="fg"><label>Stripe Webhook Secret</label><input type="password" name="swh" placeholder="whsec_..."></div>
</div>
<div class="stt">📧 SMTP (Optional)</div>
<div class="fr">
  <div class="fg"><label>SMTP Host</label><input name="mh" value="<?=htmlspecialchars($_SESSION['cfg']['mh']??'')?>" placeholder="mail.yourdomain.com"></div>
  <div class="fg"><label>SMTP Port</label><input name="mp" value="<?=htmlspecialchars($_SESSION['cfg']['mp']??'587')?>" placeholder="587"></div>
</div>
<div class="fr">
  <div class="fg"><label>SMTP Username</label><input name="mu" value="<?=htmlspecialchars($_SESSION['cfg']['mu']??'')?>" placeholder="noreply@domain.com"></div>
  <div class="fg"><label>SMTP Password</label><input type="password" name="mpw"></div>
</div>
<div class="fg"><label>From Email</label><input type="email" name="mf" value="<?=htmlspecialchars($_SESSION['cfg']['mf']??'')?>" placeholder="noreply@yourdomain.com"></div>
<button class="btn btn-p full" type="submit">Save & Continue →</button>
</form></div>

<?php elseif($step===4):?>
<div class="card">
<div class="ct">👤 Admin Account</div>
<div class="cs">Create your super admin account.</div>
<form method="POST"><input type="hidden" name="action" value="save_admin">
<div class="fg"><label>Full Name</label><input name="an" value="<?=htmlspecialchars($_SESSION['adm']['name']??'')?>" required placeholder="Muhammad Ali"></div>
<div class="fg"><label>Email</label><input type="email" name="ae" value="<?=htmlspecialchars($_SESSION['adm']['email']??'')?>" required placeholder="admin@yourdomain.com"></div>
<div class="fr">
  <div class="fg"><label>Password</label><input type="password" name="ap" required placeholder="Min. 8 characters"></div>
  <div class="fg"><label>Confirm Password</label><input type="password" name="ap2" required placeholder="Repeat"></div>
</div>
<button class="btn btn-p full" type="submit">Create Admin & Continue →</button>
</form></div>

<?php elseif($step===5): $db=$_SESSION['db']??[];$cfg=$_SESSION['cfg']??[];$adm=$_SESSION['adm']??[];?>
<div class="card">
<div class="ct">🚀 Ready to Install</div>
<div class="cs">Review your configuration before installing.</div>
<div class="cb">
  <div class="cr"><span class="cl">Database</span><span class="cv"><?=htmlspecialchars($db['name']??'')?> @ <?=htmlspecialchars($db['host']??'')?></span></div>
  <div class="cr"><span class="cl">App URL</span><span class="cv"><?=htmlspecialchars($cfg['app_url']??'')?></span></div>
  <div class="cr"><span class="cl">Admin</span><span class="cv"><?=htmlspecialchars($adm['email']??'')?></span></div>
  <div class="cr"><span class="cl">Evomi Key</span><span class="cv"><?=!empty($cfg['evomi'])?'✓ Set':'Not set'?></span></div>
  <div class="cr"><span class="cl">Stripe</span><span class="cv"><?=!empty($cfg['sk'])?'✓ Configured':'Not set'?></span></div>
</div>
<form method="POST"><input type="hidden" name="action" value="install">
<button class="btn btn-g full" type="submit">⚡ Install Now</button>
</form>
<div class="hint" style="text-align:center;margin-top:10px">Writes .env · Runs migrations · Seeds products · Creates admin</div>
</div>

<?php elseif($step===6):?>
<div class="card">
<div class="di">🎉</div>
<div class="ct" style="text-align:center;font-size:24px">Installation Complete!</div>
<div class="cs" style="text-align:center">UpgradedProxy is ready. Your platform is live!</div>
<div class="stt" style="margin-top:18px">Installation Log</div>
<?php foreach($_SESSION['wiz_logs']??[] as $l):?>
<div class="li"><span><?=$l[1]?></span></div>
<?php endforeach;?>
<div class="cb" style="margin-top:18px">
  <div class="cr"><span class="cl">🌐 Site</span><span class="cv"><?=htmlspecialchars($_SESSION['cfg']['app_url']??'')?></span></div>
  <div class="cr"><span class="cl">👤 Email</span><span class="cv"><?=htmlspecialchars($_SESSION['adm']['email']??'')?></span></div>
  <div class="cr"><span class="cl">🔑 Password</span><span class="cv">As set in Step 4</span></div>
</div>
<div class="brow">
  <a href="<?=htmlspecialchars(($_SESSION['cfg']['app_url']??'').'/login')?>" class="btn btn-p" style="flex:1;justify-content:center">🔐 Go to Login</a>
  <a href="<?=htmlspecialchars(($_SESSION['cfg']['app_url']??'').'/admin')?>" class="btn btn-s" style="flex:1;justify-content:center">🛠️ Admin Panel</a>
</div>
<div class="as" style="margin-top:16px">🔒 Wizard is now locked. Delete <code>index.php</code> after confirming everything works.</div>
</div>
<?php endif;?>
</div>
<foot>UpgradedProxy Installation Wizard — Laravel + React</foot>
</body>
</html>
