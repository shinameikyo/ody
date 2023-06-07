<?php
/**
 * 特定の条件に一致するメールからテキストを抽出し、ファイルに書き込みます。
 *
 * @param {string} $email - 検索するアカウントのメールアドレス
 * @param {string} $password - メールアカウントのパスワード
 * @param {string} $server - 接続するサーバーアドレス
 * @param {string} $subject - メールの件名を指定します。
 * @param {string} $start - メール本文から抽出するテキストの開始位置を指定します。
 * @param {string} $end - メール本文から抽出するテキストの終了位置を指定します。
 * @return {string} 成功または失敗を示すメッセージ
 */
function extract_emails($emailArray, $mysqlArray) {
    $email = $emailArray['email'];
    $emailPassword = $emailArray['pswd'];
    $server = $emailArray['server'];
    $subject = $emailArray['subject'];
    $start = $emailArray['start'];
    $end = $emailArray['end'];
    # mailサーバーにアクセス
    $mailbox = imap_open("{" . $server . "}", $email, $emailPassword);
    # mysql sessionを作成
    $mysql_session = connect_mysql_server($mysqlArray['server'], $mysqlArray['user'], $mysqlArray['pswd'], $mysqlArray['db']);
    
    // サーバーにアクセスを拒否された場合
    if ($mailbox === false) {
        return "サーバーへの接続に失敗しました";
    } else {
        // 件名が一致し、今日の日付で受信したメールをすべて確認
        $emails = imap_search($mailbox, 'SUBJECT "' . $subject .'"');
        // 条件に一致したメールリストが存在する場合
        if ($emails) {
            foreach ($emails as $email_number) {

                $message = imap_fetchbody($mailbox, $email_number, 1); # メールの本文を取得

                mb_internal_encoding('ISO-2022-JP-MS');  # mbを日本語に設定する
                $decoded_msg = mb_convert_encoding($message, 'UTF-8'); # mbでデコードを試みる

                $isJapaneseText = containsJapanese($decoded_msg); # 日本語になっているか確認

                # 日本語になっていない場合
                if (!$isJapaneseText) {
                    $decoded_msg = base64_decode($message); # base64でデコード
                    $isJapaneseText = containsJapanese($decoded_msg); #　base64で日本語になっているか確認
                    # base64で日本語になっていない場合エラー
                    if (!$isJapaneseText) { 
                        trigger_error("メール番号：$email_number の変換できない日本語が含まれているため、データベースへの書き込みは行いません。");
                    } else {
                        $res = trimMessage($decoded_msg, $start, $end); # base64で日本語が含まれる場合
                    }
                # 日本語になっている場合
                } else {
                    $res = trimMessage($decoded_msg, $start, $end); # 本文から必要な部分のみ抽出
                }
                if ($res) {
                    $lines = explode("\n", $res); # resに複数列の文がインデントされているので、インデントを境にして分ける
                    echo gettype($lines), count($lines);
                    foreach ($lines as $line) {
                        echo "item: ($line)";
                        update_mysql_table($line, $mysql_session); # mySQLのテーブルに新たなレコードの作成
                    }
                    # mb_internal_encoding('ISO-2022-JP-MS')でmbが日本語設定になっているので、デフォルトに戻す
                    mb_internal_encoding();
                    
                    return "メールが正常にファイルに書き込まれました";
                }
            }
        } else { // メールが存在しない場合の処理
            imap_close($mailbox);
            return "指定された件名の未読メールは見つかりませんでした。";
        }
        imap_close($mailbox); # メールの接続を閉じる
    }
}

# mysqlに接続
function connect_mysql_server($mysqlServer, $username, $password, $dbname) {
    $connection = mysqli_connect($mysqlServer, $username, $password, $dbname);
    if (!$connection) {
        die('connection failed: '.mysqli_connect_error());
    }
    echo "connected successfully\n";
    return $connection;
}


function update_mysql_table($res, $mysql_session) {
    # SQLのコマンド
    $sql_query = "INSERT INTO email_data (data) VALUES ('$res')";
    if ($mysql_session->query($sql_query) === TRUE) {
        echo "New record created successfully\n";
    } else {
        echo "Error: " . $sql_query . "<br>" . $mysql_session->error;
    }
}
function containsJapanese($string) {
    return preg_match('/[^\x00-\x7F]/u', $string);
}

function trimMessage($decoded_msg, $start, $end) {
    $startPos = strpos($decoded_msg, $start);
    $endPos = strpos($decoded_msg, $end, $startPos + strlen($start));
    if ($endPos === false) {
        $endPos = strlen($decoded_msg);
    }
    return trim(substr($decoded_msg, $startPos + strlen($start), $endPos - $startPos - strlen($start)));
}

# email information
$email = "officedeyasai@meikyo.co.jp";
$emailPassword = "admin5580";
$server = 'watchboot.sakura.ne.jp:993/imap/ssl/novalidate-cert';
$subject = "【OFFICE DE YASAI】";
$start = "■総務部 小柴";
$end = "========================";
# connect to email server
$emailArray = array('email' => $email, 'pswd' => $emailPassword, 'server' => $server, 'subject' => $subject, 'start' => $start, 'end' => $end);

# mysql information
$mysqlServer = "localhost";
$mysqlUser = "shina";
$mysqlPassword = "magic5580";
$dbname = "ody";
# connect to mysql server
$mysqlArray = array('server' => $mysqlServer, 'user' => $mysqlUser, 'pswd' => $mysqlPassword, 'db' => $dbname);

echo extract_emails($emailArray, $mysqlArray);


?> 
