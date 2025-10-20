<?php
session_start();
require 'db-connect.php';
require 'header.php';

function post($key)
{
    return trim($_POST[$key] ?? '');
}

try {
    $pdo = new PDO($connect, USER, PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $name     = post('name');
    $address  = post('address');
    $login    = post('login');
    $password = post('password');

    // 入力バリデーション
    $errors = [];
    if ($name === '')     $errors[] = '名前を入力してください。';
    if ($address === '')  $errors[] = '住所を入力してください。';
    if ($login === '')    $errors[] = 'ログイン名を入力してください。';
    if (strlen($password) < 6) $errors[] = 'パスワードは6文字以上にしてください。';

    if ($errors) {
        echo '<div class="container pt-3"><div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>';
        echo '</ul></div></div>';
        require 'footer.php';
        exit;
    }

    // ログイン名の重複チェック
    if (isset($_SESSION['customer'])) {
        $id  = (int)$_SESSION['customer']['id'];
        $chk = $pdo->prepare('SELECT id FROM customer WHERE id != ? AND login = ? LIMIT 1');
        $chk->execute([$id, $login]);
    } else {
        $chk = $pdo->prepare('SELECT id FROM customer WHERE login = ? LIMIT 1');
        $chk->execute([$login]);
    }
    if ($chk->fetch()) {
        echo '<div class="container pt-3"><div class="alert alert-danger">ログイン名がすでに使用されています。変更してください。</div></div>';
        require 'footer.php';
        exit;
    }

    // ハッシュ作成
    $hash = password_hash($password, PASSWORD_DEFAULT);

    if (isset($_SESSION['customer'])) {
        // 更新
        $id  = (int)$_SESSION['customer']['id'];
        $upd = $pdo->prepare('UPDATE customer SET name=?, address=?, login=?, password=? WHERE id=?');
        $upd->execute([$name, $address, $login, $hash, $id]);

        // セッション更新
        $_SESSION['customer'] = [
            'id'      => $id,
            'name'    => $name,
            'address' => $address,
            'login'   => $login,
        ];
        echo '<div class="container pt-3"><div class="alert alert-success">お客様情報を更新しました。</div></div>';
    } else {
        // 新規登録
        $ins = $pdo->prepare('INSERT INTO customer (name, address, login, password) VALUES (?, ?, ?, ?)');
        $ins->execute([$name, $address, $login, $hash]);

        // 自動ログイン（任意）
        $newId = (int)$pdo->lastInsertId();
        $_SESSION['customer'] = [
            'id'      => $newId,
            'name'    => $name,
            'address' => $address,
            'login'   => $login,
        ];
        echo '<div class="container pt-3"><div class="alert alert-success">お客様情報を登録しました。ログイン済みです。</div></div>';
    }

    echo '<div class="container pb-3"><a class="btn btn-primary" href="index.php">トップへ</a></div>';
} catch (Throwable $e) {
    // エラーメッセージを出す（本番ではログに記録推奨）
    echo '<div class="container pt-3"><div class="alert alert-danger">登録処理でエラーが発生しました。</div></div>';
}
require 'footer.php';
