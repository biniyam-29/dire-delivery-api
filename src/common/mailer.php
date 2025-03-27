<?php
namespace src\common;
require 'vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    
    private function __construct() {
        $this->mail = new PHPMailer(true);

        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'bnmmarkos@gmail.com';
        $this->mail->Password = 'nmgwfrqnpnyubntc';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
    }

    public static function getInstance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new Mailer();
        }
        return $instance;
    }

    public function send($to, $subject, $body) {
        try {
            $this->mail->setFrom('bnmmarkos@gmail.com', 'Biniyam');
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
