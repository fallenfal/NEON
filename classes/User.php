<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include(realpath($_SERVER["DOCUMENT_ROOT"])). '/neon/classes/PHPMailer/Exception.php';
include(realpath($_SERVER["DOCUMENT_ROOT"])). '/neon/classes/PHPMailer/PHPMailer.php';
include(realpath($_SERVER["DOCUMENT_ROOT"])). '/neon/classes/PHPMailer/SMTP.php';

class User extends Main{

    public $id;
    public $firstName;
    public $lastName;
    public $username;
    public $password;
    public $validationCode;
    public $active;
    public $role;
    public $dob;
    public $userDescription;
    public $email;
    public $phone;
    public $recoveryEmail;
    public $created_at;
    public $deleted_at;
    public $cookieConsent;


    private $table = "users";
    public $errors = [];

    public function displayErrors($err){
        if(!empty($err)){
            if(is_array($err)){
                foreach ($err as $error){
                    self::displayValidationErrors($error);
                }
            } else {
                self::displayValidationErrors($err);
            }
        }
    }

    public function setToken(){
        return $token = sha1($this->username.microtime());
    }

    public function sentEmail($email, $subject, $msg,$name = null,$headers = null){

        $mail = new PHPMailer;
        $mail->isSMTP();
        $mail->SMTPDebug = 0; // 0 = off (for production use) - 1 = client messages - 2 = client and server messages
        $mail->Host = "smtp.gmail.com"; // use $mail->Host = gethostbyname('smtp.gmail.com'); // if your network does not support SMTP over IPv6
        $mail->Port = 587; // TLS only
        $mail->SMTPSecure = 'tls'; // ssl is depracated
        $mail->SMTPAuth = true;
        $mail->Username = "lapos.alexgabriel@gmail.com";
        $mail->Password = "yxftisinmydgndkj";
        $mail->setFrom("lapos.alex88@gmail.com", "Alex");
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->msgHTML($msg); //$mail->msgHTML(file_get_contents('contents.html'), __DIR__); //Read an HTML message body from an external file, convert referenced images to embedded,
        $mail->AltBody = 'HTML messaging not supported';
// $mail->addAttachment('images/phpmailer_mini.png'); //Attach an image file

        if(!$mail->send()){
            return $this->errors = $mail->ErrorInfo;
        }else{
            return true;
        }
    }

    private function getAllUsersEmails(){
        $query = "SELECT email FROM users";

        $all =  $this->selectAll($query);
        return $all;
    }
    private function getInfoForValidation($email){
        $query = "SELECT * FROM ".$this->table." WHERE email= '$email'";
         $all =  $this->selectAll($query);
         return $all[0];
    }

    private function getUser($email){
        if(!empty($email) || $email !== null){
        $query = "SELECT * FROM ".$this->table." WHERE email='$email'";
        $all =  $this->selectAll($query);
            return $all[0];
        }
    }


    public function registerUser($params){
        // assign values to each property from the class
        foreach ($params as $key => $param) {
            $this->$key = $param;
        }

        // Validate the input
        //max caracter 20 , min 3
        if(strlen($this->username) < 3 || strlen($this->username) > 20){
            $this->errors[] = "Username must be greater than 3 and lower than 20 characters";
        }
        if (!preg_match('/^[A-Za-z][A-Za-z]{5,31}$/', "$this->username")){
            $this->errors[] = "Username must contain only <strong>Letters</strong> and at least 6 characters";
        }
        if (!preg_match('/(^(?=.*\d))^[A-Za-z][A-Za-z0-9]{5,31}$/', "$this->password")){
            $this->errors[] = "Password must contain only <strong>Letters and Numbers</strong>  and at least 6 characters";
        }

        //check if empty
        if(empty($this->email) || $this->email === null){
            $this->errors[] = "Field cannot be empty";
        }
        if (empty($this->password)) {
            $this->errors[] = "Password field cannot be <strong>EMPTY</strong>";
        }

        //check if the email exists
        $registeredUsers = $this->getAllUsersEmails();
        if(array_search($this->email, $registeredUsers,true)) {
            $this->errors[] = "Email already exists, try another email!";
        }

        if(!empty($this->errors)){
            return $this->displayErrors($this->errors);
        }

        $securedPassword = password_hash($this->password,PASSWORD_DEFAULT);


        $newParams = [
            'email' => $this->email,
            'username' => $this->username,
            'password' => $securedPassword,
            'validationCode' => $this->validationCode,
            'active' => "0",
            'created_at' => $this->created_at
        ];

        $query = "INSERT INTO ". $this->table ." (". $this->matchKeys($newParams).") VALUES 
            (". $this->matchValues($newParams) .")";

        if($this->insert($query,$newParams)){
            $email = $newParams['email'];
            $subject = "Complete registration Email";
            $msg = "<a href='".__FILE__ ."?email={$newParams['email']}&validationCode={$newParams['validationCode']}'>".__FILE__ ."?email={$newParams['email']}&validationCode={$newParams['validationCode']}</a>
            HERE WILL BE THE LINKS FOR THE ACTIVATION
            EMAIL {$newParams['email']} <br>
            AC {$newParams['validationCode']}
            ";
            $name = $newParams['username'];

            if($this->sentEmail($email, $subject, $msg,$name)){
                return true;
            } else {
                return $this->displayErrors("Something went Wrong");
            }

        }
    }


    public function activateUser($params){
        // assign values to each property from the class
        foreach ($params as $key => $param) {
            $this->$key = $param;
        }

        $dbInfo = $this->getInfoForValidation($this->email);
        $email = $dbInfo['email'];
        $validationCode = $dbInfo['validationCode'];

        echo "<br>";
        if($this->email === $email && $this->validationCode === $validationCode){
            $active = '1';
            $validationCode = "0";
            $newParams = [
                'active' => $active,
                'validationCode'=>$validationCode
            ];
            $email=$dbInfo['email'];

            $query = "UPDATE ".$this->table." SET active=:active,validationCode=:validationCode WHERE email='$email'";

            return $this->update($query,$newParams);
        } else {
            echo "BAD";
        }

    }

    public function verifyUSer($params){

        // assign values to each property from the class
        foreach ($params as $key => $param) {
            $this->$key = $param;
        }
        //get user info based by email
        $allUserData = $this->getUser($this->email);

        $dbEmail = $allUserData['email'];
        $dbPassword = $allUserData['password'];
        $dbActive = $allUserData['active'];

        if($dbActive != '1'){
            $this->errors[] = "User is not active. Check your email to activate your account";
        }

        if(empty($this->email)){
            $this->errors[] = "Email field cannot be empty!";
        }

        if(empty($this->password)){
            $this->errors[] = "Password field cannot be empty!";
        }

        if (!preg_match('/(^(?=.*\d))^[A-Za-z][A-Za-z0-9]{5,31}$/', "$this->password")){
            $this->errors[] = "Password must contain only <strong>Letters and Numbers</strong>  and at least 6 characters";
        }

        if(!empty($this->errors)){
            return $this->displayErrors($this->errors);
        } else {



            if($dbEmail === $this->email){
                if(password_verify($this->password, $dbPassword)){
                    return $allUserData;
                } else {
                    $this->errors[]= "Password dosent match";
                }
            } else {
                $this->errors[] = "Email dosent exist";
            }

        }

    }



    public function addUser($params){
        // assign values to each property from the class
        foreach ($params as $key => $param) {
            $this->$key = $param;
        }
        //get user info based by email
        $allUserData = $this->getUser($this->email);

        $dbEmail = !empty($allUserData['email']);
        $dbPassword = !empty($allUserData['password']);
        if($dbEmail !== NULL){
            if($dbEmail == $this->email){
                $this->errors[] = "Email already exists!";
            }
        }


        if(empty($this->email)){
            $this->errors[] = "Email field cannot be empty!";
        }

        if(empty($this->password)){
            $this->errors[] = "Password field cannot be empty!";
        }

        if (!preg_match('/(^(?=.*\d))^[A-Za-z][A-Za-z0-9]{5,31}$/', "$this->password")){
            $this->errors[] = "Password must contain only <strong>Letters and Numbers</strong>  and at least 6 characters";
        }

        if(!empty($this->errors)){
            return $this->displayErrors($this->errors);
        } else {
            $securedPassword = password_hash($this->password,PASSWORD_DEFAULT);
            $validationCode = $this->setToken();
            $validParams = [
                'firstName' =>$this->firstName,
                'lastName' =>$this->lastName,
                'username' =>$this->username,
                'email' =>$this->email,
                'password' =>$securedPassword,
                'role' =>$this->role,
                'dob' =>$this->dob,
                'phone' =>$this->phone,
                'recoveryEmail' =>$this->recoveryEmail,
                'active' =>'0',
                'ValidationCode' => $validationCode
            ];

            $query = "INSERT INTO ". $this->table ." (". $this->matchKeys($validParams).") VALUES 
            (". $this->matchValues($validParams) .")";;

            $new =  $this->insert($query,$validParams);



            if($new){
                $email = $this->email;
                $subject = "Complete registration Email";
                $msg = "<a href='".__FILE__ ."?email={$this->email}&validationCode={$validationCode}'>".__FILE__ ."?email={$this->email}&validationCode={$validationCode}</a>
                        HERE WILL BE THE LINKS FOR THE ACTIVATION <br>
                        EMAIL {$this->email} <br>
                        AC {$validationCode}
                        ";
                $name = $this->username;
                $this->sentEmail($email, $subject, $msg,$name);
                return $new;
            }

        }




    }

    public function getUserByID($id){

        $query = "SELECT * FROM ". $this->table ." WHERE id=".$id;

        $result =  $this->selectAll($query);

        return $result[0];
    }

    public function updateUser($params,$id){

        // assign values to each property from the class
        foreach ($params as $key => $param) {
            $this->$key = $param;
        }
        //get user info based by email
        $userData = $this->getUserByID($id);

        $dbEmail = $userData['email'];
        $dbPassword = $userData['password'];

        $activeEmails = $this->getAllUsersEmails();
//        foreach ($activeEmails as $key => $value){
//            if ($value['email'] == $this->email) {
//                unset($activeEmails[$key]);
//            }
//        }
        $newemails = [];
        foreach($activeEmails as $key => $value){
            foreach ($value as $key2 => $value2){
                $newemails[] = $value2;
            }

        }


        if($dbEmail != $this->email){
        foreach ($newemails as $key => $value){
            if ($value == $this->email) {
                unset($activeEmails[$key]);
            }
        }
            if(in_array($this->email,$newemails)){
                $this->errors[] = "Email already exists!";
            }
        }


        if(password_verify($dbPassword, $this->password) !== password_verify($this->password, $dbPassword)){
            if (!preg_match('/(^(?=.*\d))^[A-Za-z][A-Za-z0-9]{5,31}$/', "$this->password")){
                $this->errors[] = "Password must contain only <strong>Letters and Numbers</strong>  and at least 6 characters";
            }
            $securedPassword = password_hash($this->password, PASSWORD_DEFAULT);
        }

        if(empty($this->email)){
            $this->errors[] = "Email field cannot be empty!";
        }

        if(empty($this->password)){
            $this->errors[] = "Password field cannot be empty!";
        }


        if(!empty($this->errors)){
            $this->displayErrors($this->errors);
        } else {

            $validParams = [
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'username' => $this->username,
                'email' => $this->email,
                'role' => $this->role,
                'dob' => $this->dob,
                'phone' => $this->phone,
                'recoveryEmail' => $this->recoveryEmail,
            ];
            if(!empty($securedPassword)){
                $validParams['password'] = $securedPassword;
            }
            $attributes = $this->setAttributesForUpdate($validParams);

            $query = "UPDATE " . $this->table . " SET " . $attributes . " WHERE id=" . $id;

            $result = $this->update($query,$validParams);
            return $result;
        }
    }














} //END OF CLASS