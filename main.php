
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
function extract_emails($emailArray, $mysqlArray) {
    $email = $emailArray['email'];
    $emailPassword = $emailArray['pswd'];
    $server = $emailArray['server'];
    $subject = $emailArray['subject'];
    $start = $emailArray['start'];
    $end = $emailArray['end'];
    $mailbox = imap_open("{" . $server . "}", $email, $emailPassword);
    # サーバーにアクセスを拒否された場合
    if ($mailbox === false) {
        return "Failed to connect to server";
    } else {
        # subjectと件名が一致して、今日付で届いたメールを全て確認
        $emails = imap_search($mailbox, 'SUBJECT "' . $subject .'"');

        if ($emails) {
            foreach ($emails as $email_number) {
                $message = imap_fetchbody($mailbox, $email_number, 1);
                $charset = mb_detect_encoding($message);
                            
                mb_internal_encoding('ISO-2022-JP-MS');
                $decoded_msg = mb_convert_encoding($message, 'UTF-8');
                $isJapaneseText = containsJapanese($decoded_msg);
                if (!$isJapaneseText) {
                    $decoded_msg = base64_decode($message);
                    $isJapaneseText = containsJapanese($decoded_msg);
                    if (!$isJapaneseText) {
                        trigger_error("email number: $email_number は日本語変換できないため、DBへの書き込みは行いません。");
                    } else {
                        # 日本語が含まれた文の処理
                        $res = trimMessage($decoded_msg, $start, $end);
                    }
                } else {
                    $res = trimMessage($decoded_msg, $start, $end);
                }
                if ($res) {
                    echo "email num: $email_number";
                    echo "charset: $charset";
                    echo "decoded message: $decoded_msg";

                    $mysql_session = connect_mysql_server($mysqlArray['server'], $mysqlArray['user'], $mysqlArray['pswd'], $mysqlArray['db']);
                    
                    echo($res);
                    
                    # SQLのレコード作成
                    update_mysql_table($res, $mysql_session);
                
                    mb_internal_encoding();
                    
                    return "Emails written to file successfully";
                }
            }
        } else { # emailsが存在しない時の処理
            imap_close($mailbox);
            return "No unread emails with the specified subject were found.";
        }
        imap_close($mailbox);
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
