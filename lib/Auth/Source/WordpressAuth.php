<?php
class sspmod_wordpressauth_Auth_Source_WordpressAuth extends sspmod_core_Auth_UserPassBase {

    /* The database DSN */
    private $dsn;

    /* The database username & password. */
    private $username;
    private $password;

    /* Table name for users tables (usually wp_users) */
    private $userstable;

    public function __construct($info, $config) {
        parent::__construct($info, $config);

        /* Load DSN, username, password and userstable from configuration */
        if (!is_string($config['dsn'])) {
            throw new Exception('Missing or invalid dsn option in config.');
        }
        $this->dsn = $config['dsn'];
        if (!is_string($config['username'])) {
            throw new Exception('Missing or invalid username option in config.');
        }
        $this->username = $config['username'];
        if (!is_string($config['password'])) {
            throw new Exception('Missing or invalid password option in config.');
        }
        $this->password = $config['password'];
        if (!is_string($config['userstable'])) {
            throw new Exception('Missing or invalid userstable option in config.');
        }
        $this->userstable = $config['userstable'];
    }

    protected function login($username, $password) {
        /* Connect to the database. */
        $db = new PDO($this->dsn, $this->username, $this->password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        /* Ensure that we are operating with UTF-8 encoding. */
        $db->exec("SET NAMES 'utf8'");

        /* Prepare statement (PDO) */
        $st = $db->prepare('SELECT user_login, meta_key, meta_value, user_pass, display_name, user_email FROM '.$this->userstable.' join wp_usermeta on wp_users.id = wp_usermeta.user_id WHERE user_login = :username AND wp_usermeta.meta_key = "wp_capabilities"');

        if (!$st->execute(array('username' => $username))) {
            throw new Exception("Failed to query database for user.");
        }

        /* Retrieve the row from the database. */
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            /* User not found. */
            throw new SimpleSAML_Error_Error('WRONGUSERPASS');
        }

        /* Load the Portable PHP password hashing framework */
        require_once( dirname(__FILE__).'/../../../vendor/PasswordHash.php' );
        $hasher = new PasswordHash(8, TRUE);

        /* Check the password against the hash in Wordpress wp_users table */
        if (!$hasher->CheckPassword($password, $row['user_pass'])){
            /* Invalid password. */
            throw new SimpleSAML_Error_Error('WRONGUSERPASS');
        }
        preg_match('/"(.*)"/', $row['meta_value'], $memberOf);

        /* Create the attribute array of the user. */
        $attributes = array(
            'uid' => array($username),
            'username' => array($username),
            'name' => array($row['display_name']), 
            'displayName' => array($row['display_name']),
            'email' => array($row['user_email']),
            'eduPersonAffiliation' => array('member', 'employee'),
            'MemberOf' =>  $memberOf
        );

        /* Return the attributes. */
        return $attributes;
    }
}
