<?php
class DB{
    private static $db = null;
	private $mysqli;
    private const HOST = 'localhost';
    private const USER = 'root';
    private const PASSWORD = '';
    private const NAME = 'db_prodg-230516';

	private function __construct(){
		$this->mysqli = @new mysqli(self::HOST, self::USER,self::PASSWORD,self::NAME);
		$this->mysqli->query("SET lc_time_names = 'ru_RU'");
		$this->mysqli->set_charset("utf8");
	}
    public static function get_DB(){
		if(self::$db == null) self::$db = new DB();
		return self::$db;
	}
    
	public function query($query, $op = 'SELECT'){
		$success = $this->mysqli->query($query);
        if($op == 'SELECT') $success = $success->fetch_assoc();
        return $success;
	}
}
class Auth_User{
    private $db;
    private $data;
    private $result = ['result'=>false];

    public function __construct(){
        $this->data = $this->xxs($_REQUEST);
        $this->db = DB::get_DB();
    }

    private function xxs($data){
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->xxs($value);
            }
            return $result;
        }
        else{
            return htmlspecialchars($data);
        }
    }

    public function auth($login, $password){
        if($login == '' || $password == ''){
          if(isset($_COOKIE['cookie']) && is_int((int)$_COOKIE['cookie'])){
            $query = 'SELECT * FROM `users` WHERE `id` = "'.$this->xxs($_COOKIE['cookie']).'"';
            $success = $this->db->query($query);
            if(is_array($success) && Counter::get_permission($success)) $this->ok($success);
          }
        }
        else{
          $login = mb_strtolower($login);
          $query = 'SELECT * FROM `users` WHERE `login` = "'.$login.'"';
          $success = $this->db->query($query);
          if(!is_array($success)){
              $this->data['result'] = 'no_data';
              return false;
          };
          if(!Counter::get_permission($success)){
              $this->data['result'] = 'forbidden';
              return false;
          }
          if(isset($this->data['cookie']) && $this->data['cookie'] === $this->data['id'])
              $password = $this->data['cookie_password']??'';
          else 
              $password = md5($password);
          if(!is_array($success) || count($success) == 0) {
              $this->data['result'] = 'non_login';
              return false;
          }
          if($success['login'] !== $login || $success['password'] !== $password){
              $this->data['result'] = 'error_login';
              return false;
          }
          $this->ok($success);
        }
    }
    
    private function ok($data){
        Counter::reset($data);
        $this->set_cookie($data['login'], $data['password']);
        $this->set_cookie('cookie', $data['id']);

        $this->data['result'] = true;
        $this->data['f_name'] = $data['f_name'];
        $this->data['l_name'] = $data['l_name'];
        $this->data['m_name'] = $data['m_name'];
        $this->data['h_d'] = $data['h_d'];
        $this->data['photo'] = $data['photo'];
    }
    
    private function set_cookie($name, $value){
        return setcookie(
            $name,
            $value,
            time()+60*60*24*30,
            '/'
        );
    }

    public function get_field(string $field){
        if(isset($this->$field)) return $this->$field;
        return false;
    }

    public function __toString(){
        return json_encode($this->data);
    }
}
class Counter{

    public static function get_permission($data){
        $number = $data['number'];
        $query_array = [];
        $result = false;
        if($number < 12){
            $number++;
            $query_array['number']= $number;
            $result = true;
        }
        else if($data['number'] >= 12 && $data['fine'] == 0) {
            $query_array['fine'] = time() + 720;
        }
        else if(time() > $data['fine']){
            $query_array['fine'] = 0;
            $query_array['number'] = 1;
            $result = true;
        }
        if(count($query_array) > 0)
            self::go_query(self::get_query($query_array,'`login`="'.$data['login'].'"'));

        return $result;
    }

    private static function go_query($query){
        $db = DB::get_DB();
        return $db->query($query,'UPDATE');
    }

    private static function get_query(array $data, $where){
        $query = 'UPDATE `users` SET ';
        foreach ($data as $key => $value) {
            $query .= '`'.$key.'`="'.$value.'",';
        }
        $query = substr($query, 0, -1);
        return $query .=' WHERE '.$where;
    }

    public static function reset($data){
        $query['number'] = 0;
        $query['fine'] = 0;
        self::go_query(self::get_query($query,'`login`="'.$data['login'].'"'));
        return true;
    }

}
//$data = $_POST;
$data = $_REQUEST;
$flag = $data['start']??false;
if($flag !== '1' || !isset($data['password_user']) || !isset($data['login'])) echo json_encode(['result'=>false]);
else{
    $auth = new Auth_User();
    $auth->auth($auth->get_field('data')['login'], $auth->get_field('data')['password_user']);
    echo $auth;
}
exit();

?>