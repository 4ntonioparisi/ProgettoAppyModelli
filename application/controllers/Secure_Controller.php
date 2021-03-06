<?php 

class Secure_Controller extends CI_Controller 
{
	/*
	* Controllers that are considered secure extend Secure_Controller, optionally a $module_id can
	* be set to also check if a user can access a particular module in the system.
	*/
	public function __construct($module_id = null, $submodule_id = null)
	{
		parent::__construct();
		
		$this->load->model('Employee');
		$model = $this->Employee;

		if(!$model->is_logged_in())
		{
			redirect('login');
		}

		$this->track_page($module_id, $module_id);

		$logged_in_employee_info = $model->get_logged_in_employee_info();
		if(!$model->has_module_grant($module_id, $logged_in_employee_info->person_id) || 
			(isset($submodule_id) && !$model->has_module_grant($submodule_id, $logged_in_employee_info->person_id)))
		{
			redirect('no_access/' . $module_id . '/' . $submodule_id);
		}

		// load up global data visible to all the loaded views
		$data['allowed_modules'] = $this->Module->get_allowed_modules($logged_in_employee_info->person_id);
		$data['user_info'] = $logged_in_employee_info;
		$data['controller_name'] = $module_id;

		$this->load->vars($data);
	}
	
	/*
	* Internal method to do XSS clean in the derived classes
	*/
	protected function xss_clean($str, $is_image = false)
	{
		// This setting is configurable in application/config/config.php.
		// Users can disable the XSS clean for performance reasons
		// (cases like intranet installation with no Internet access)
		if($this->config->item('ospos_xss_clean') == false)
		{
			return $str;
		}
		else
		{
			return $this->security->xss_clean($str, $is_image);
		}
	}


	// this is the basic set of methods most OSPOS Controllers will implement
	public function index() { return false; }
	public function search() { return false; }
	public function suggest_search() { return false; }
	public function view() { return false; }
	public function save() { return false; }
	public function delete() { return false; }

}
?>