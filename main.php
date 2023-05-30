<?php
# TODO The decode is not working
/**
Extracts text from emails that match specific criteria and writes it to a file.
@param {string} $email - Email address of the account to search.
@param {string} $password - Password of the email account.
@param {string} $server - Server address to connect to.
@param {string} $subject - The subject line to look for in the emails.
@param {string} $start - The start of the text to extract from the email body.
@param {string} $end - The end of the text to extract from the email body.
@return {string} A message indicating success or failure.
*/
function extract_emails($email, $password, $server, $subject, $start, $end) {
    $mailbox = imap_open("{" . $server . "}", $email, $password);
    # サーバーにアクセスを拒否された場合
    if ($mailbox === false) {
        return "Failed to connect to server";
    } else {
        # subjectと件名が一致して、今日付で届いたメールを全て確認
        $emails = imap_search($mailbox, 'SUBJECT "' . $subject .'"');
        if ($emails) {
            # 指定したファイルを上書きモードで開く
            $file = fopen("output.txt", "w");
            # 該当するmailの数だけ for loop
            foreach ($emails as $email_number) {
                # 本文の取得
                $message = imap_fetchbody($mailbox, $email_number, 1);
                # 元データがhtmlなのでdecode
                $decoded_msg = base64_decode($message);
                # 変数に指定したstartとendで囲まれた文のみ抽出
                $startPos = strpos($decoded_msg, $start);
                $endPos = strpos($decoded_msg, $end, $startPos + strlen($start));
                $res = trim(substr($decoded_msg, $startPos + strlen($start), $endPos - $startPos - strlen($start)));
                echo $res;
                # ファイルに書き込み
                fwrite($file, $res."\n");
            }
            fclose($file);
            imap_close($mailbox);
            return "Emails written to file successfully";
        } else { # emailsが存在しない時の処理
            imap_close($mailbox);
            return "No unread emails with the specified subject were found.";
        }
    }
}

# mysqlに接続
function connect_mysql_server($mysqlServer, $username, $password, $dbname) {
    $connection = mysqli_connect($mysqlServer, $username, $password, $dbname);
    if (!$connection) {
        die('connection failed: '.mysqli_connect_error());
    }
}
echo "connected successfully";

# email information
$email = "shinagawa@meikyo.co.jp";
$password = "pass47111";
$server = 'watchboot.sakura.ne.jp:993/imap/ssl/novalidate-cert';
$subject = "【OFFICE DE YASAI】";
$start = "■総務部 小柴";
$end = "========================";
# connect to email server
// echo extract_emails($email, $password, $server, $subject, $start, $end);
# mysql information
$mysqlServer = "localhost";
$username = "shina";
$password = "magic5580";
$dbname = "ody";
# connect to mysql server
echo connect_mysql_server($mysqlServer, $username, $password, $dbname);
?> 
