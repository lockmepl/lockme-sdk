<?php
namespace Lockme;

class Lockme{
  const API_URL="https://lockme.pl/api/";
  const API_VERSION = "1.0";

  private $key;
  private $secret;

  function __construct($key, $secret){
    $this->key = $key;
    $this->secret = $secret;
  }

  public function Decrypt(){
    $body = file_get_contents("php://input");
    $hash = sha1($body.$this->secret);
    if($hash !== $_SERVER['HTTP_SIGNATURE']){
      throw new LockMe_Exception("Wrong signature");
    }
    return json_decode($body, true);
  }

  public function Test(){
    return $this->_request("test");
  }

  public function RoomList(){
    return $this->_request("rooms");
  }

  public function Reservation($id){
    return $this->_request("reservation/{$id}");
  }

  public function AddReservation($data){
    if(!$data['roomid']){
      throw new \Exception("No room ID");
    }
    if(!$data["date"]){
      throw new \Exception("No date");
    }
    if(!$data["hour"]){
      throw new \Exception("No hour");
    }
    return $this->_request("reservation", 'PUT', $data);
  }

  public function DeleteReservation($id, $data = array()){
    return $this->_request("reservation/{$id}", 'DELETE', $data);
  }

  public function EditReservation($id, $data){
    return $this->_request("reservation/{$id}", 'POST', $data);
  }

  public function PricerList(){
    return $this->_request("pricers");
  }

  private function _request($url, $method='GET', $params=array()){
    $params = json_encode($params);
    $hash = sha1($method.$url.$params.$this->secret);

    $ch = curl_init(self::API_URL."/v".self::API_VERSION."/".$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Content-Type: application/json",
      "Partner-Key: {$this->key}",
      "Signature: {$hash}"
    ));
    switch($method){
      case 'GET':
      break;
      case 'PUT':
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($ch, CURLOPT_POSTFIELDS,$params);
      break;
      case 'POST':
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS,$params);
      break;
      case 'DELETE':
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      curl_setopt($ch, CURLOPT_POSTFIELDS,$params);
      break;
    }
    $ret = curl_exec($ch);
    $ret = json_decode($ret, true);
    if(is_array($ret) && array_key_exists('error', $ret)){
      throw new LockMe_Exception($ret['error']);
    }
    return $ret;
  }
}
class LockMe_Exception extends \Exception{}
