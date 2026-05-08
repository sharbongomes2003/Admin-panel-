<?php
// ১. CORS এবং সিকিউরিটি হেডার (অ্যাপ থেকে কানেকশনের জন্য এটি বাধ্যতামূলক)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// ২. ডাটা সংরক্ষণের জন্য ফাইলগুলোর নাম
$user_file = 'users.json';
$request_file = 'requests.json';

// ৩. ফাইল না থাকলে তৈরি করে নেওয়া
if (!file_exists($user_file)) {
    file_put_contents($user_file, json_encode([]));
    chmod($user_file, 0777); // রাইট পারমিশন সেট করা
}
if (!file_exists($request_file)) {
    file_put_contents($request_file, json_encode([]));
    chmod($request_file, 0777); // রাইট পারমিশন সেট করা
}

$action = $_GET['action'] ?? 'admin';
$device_id = $_REQUEST['device_id'] ?? '';

// --- ৪. ফাংশনাল লজিক শুরু ---

// (A) পেমেন্ট রিকোয়েস্ট জমা নেওয়া
if ($action == "submit") {
    $requests = json_decode(file_get_contents($request_file), true);
    $requests[] = [
        'device_id' => $device_id,
        'trx_id' => $_POST['trx_id'] ?? 'N/A',
        'days' => (int)($_POST['days'] ?? 0),
        'time' => time()
    ];
    file_put_contents($request_file, json_encode($requests));
    exit("success");
}

// (B) ১ দিনের ফ্রি ট্রায়াল প্রদান
if ($action == "trial") {
    $users = json_decode(file_get_contents($user_file), true);
    if (!isset($users[$device_id])) {
        $users[$device_id] = time() + 86400; // ২৪ ঘণ্টা যোগ
        file_put_contents($user_file, json_encode($users));
        exit("trial_started");
    }
    exit("trial_already_used");
}

// (C) লাইসেন্স স্ট্যাটাস চেক (অ্যাপ এই অংশটি কল করে)
if ($action == "check") {
    $users = json_decode(file_get_contents($user_file), true);
    if (isset($users[$device_id])) {
        $expiry = $users[$device_id];
        if ($expiry > time()) {
            echo "active|" . $expiry;
        } else {
            echo "expired";
        }
    } else {
        echo "inactive";
    }
    exit;
}

// (D) অ্যাডমিন কর্তৃক অনুমোদন (Approve)
if ($action == "approve") {
    $days = (int)($_GET['days'] ?? 0);
    $users = json_decode(file_get_contents($user_file), true);
    $requests = json_decode(file_get_contents($request_file), true);

    // ইউজারের জন্য মেয়াদ সেট করা
    $users[$device_id] = time() + ($days * 86400);
    file_put_contents($user_file, json_encode($users));

    // পেন্ডিং রিকোয়েস্ট থেকে মুছে ফেলা
    foreach ($requests as $key => $req) {
        if ($req['device_id'] == $device_id) {
            unset($requests[$key]);
            break;
        }
    }
    file_put_contents($request_file, json_encode(array_values($requests)));
    
    header("Location: api.php?action=admin");
    exit;
}

// --- ৫. অ্যাডমিন ইন্টারফেস (ব্রাউজার ভিউ) ---

if ($action == "admin") {
    $requests = json_decode(file_get_contents($request_file), true);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Panel - Shrabon Gomez</title>
        <style>
            body { background: #001529; color: white; font-family: sans-serif; padding: 20px; }
            .header { color: #00FFFF; border-bottom: 2px solid #002140; padding-bottom: 10px; margin-bottom: 20px; }
            .card { background: #002140; padding: 20px; margin-bottom: 15px; border-left: 5px solid #2979FF; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
            .btn-approve { background: #2979FF; color: white; padding: 10px 20px; text-decoration: none; float: right; border-radius: 5px; font-weight: bold; transition: 0.3s; }
            .btn-approve:hover { background: #00E676; }
            .info { color: #00FF00; font-weight: bold; }
            .empty { text-align: center; color: #555; margin-top: 50px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>★ MOD ADMIN DASHBOARD ★</h2>
            <small>Status: No-Database JSON Mode</small>
        </div>

        <?php if (empty($requests)): ?>
            <div class="empty">কোনো পেন্ডিং পেমেন্ট রিকোয়েস্ট নেই।</div>
        <?php else: ?>
            <?php foreach ($requests as $r): ?>
                <div class="card">
                    <b>DEVICE ID:</b> <?php echo htmlspecialchars($r['device_id']); ?><br>
                    <b>TRX ID:</b> <span class="info"><?php echo htmlspecialchars($r['trx_id']); ?></span><br>
                    <b>PLAN:</b> <?php echo $r['days']; ?> Days 
                    <a href="api.php?action=approve&device_id=<?php echo $r['device_id']; ?>&days=<?php echo $r['days']; ?>" class="btn-approve">APPROVE</a>
                    <div style="clear: both;"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}
?>