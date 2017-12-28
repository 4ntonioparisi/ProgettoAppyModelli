<?php


	protected function track_page($path, $page)
	{
		if(get_instance()->Appconfig->get('statistics'))
		{
			$this->load->library('tracking_lib');

			if(empty($path))
			{
				$path = 'home';
				$page = 'home';
			}

			$this->tracking_lib->track_page('controller/' . $path, $page);
		}
	}

	protected function track_event($category, $action, $label, $value = NULL)
	{
		if(get_instance()->Appconfig->get('statistics'))
		{
			$this->load->library('tracking_lib');

			$this->tracking_lib->track_event($category, $action, $label, $value);
		}
	}

	public function numeric($str)
	{
		return parse_decimals($str);
	}

	public function check_numeric()
	{
		$result = TRUE;

		foreach($this->input->get() as $str)
		{
			$result = parse_decimals($str);
		}

		echo $result !== FALSE ? 'true' : 'false';
	}