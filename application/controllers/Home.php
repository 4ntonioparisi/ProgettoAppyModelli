<?php 

require_once("Secure_Controller.php");

class Home extends Secure_Controller 
{
	public function index()
	{
		$this->load->view('home');
	}

	public function logout()
	{
		$this->track_page('logout', 'logout');

		$this->Employee->logout();
	}
}
?>
