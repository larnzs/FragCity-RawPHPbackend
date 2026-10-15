case 'request_withdraw':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    $amount = (int)($_POST['amount'] ?? 0);
    $card   = trim($_POST['card'] ?? '');
    if ($amount < 10000) {
        echo json_encode(['error' => 'حداقل مبلغ برداشت ۱۰,۰۰۰ تومان است.']);
        exit;
    }
    if (empty($card)) {
        echo json_encode(['error' => 'شماره کارت / شبا الزامی است.']);
        exit;
    }
    // بررسی موجودی
    $wallet = $db->select("SELECT balance FROM user_wallets WHERE steamid = ?", [$steamid]);
    $balance = $wallet ? (int)$wallet[0]['balance'] : 0;
    if ($balance < $amount) {
        echo json_encode(['error' => 'موجودی کافی نیست.']);
        exit;
    }
    // درج درخواست برداشت
    $db->query("INSERT INTO withdraw_requests (steamid, amount, card_number, status) VALUES (?, ?, ?, 'pending')", [$steamid, $amount, $card]);
    // (اختیاری) می‌توان موجودی را موقتاً کم کرد یا تا زمان تأیید ادمین دست نزد
    echo json_encode(['success' => true, 'message' => 'درخواست شما ثبت شد.']);
    break;