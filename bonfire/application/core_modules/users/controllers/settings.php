<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
	Copyright (c) 2011 Lonnie Ezell

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
*/

class Settings extends Admin_Controller
{

	//--------------------------------------------------------------------

	public function __construct()
    {
			parent::__construct();

			$this->auth->restrict('Site.Settings.View');
			$this->auth->restrict('Bonfire.Users.View');

			$this->load->model('roles/role_model');

			$this->lang->load('users');

			Template::set_block('sub_nav', 'settings/sub_nav');
	}

	//--------------------------------------------------------------------

	public function _remap($method)
	{
			if (method_exists($this, $method))
			{
					$this->$method();
			}
	}

	//--------------------------------------------------------------------

	public function index()
	{

			$roles = $this->role_model->select('role_id, role_name')->where('deleted', 0)->find_all();
			Template::set('roles', $roles);

			$offset = $this->uri->segment(5);

			// Do we have any actions?
			$action = $this->input->post('submit').$this->input->post('delete').$this->input->post('purge');

			if (!empty($action))
			{
				$checked = $this->input->post('checked');

				switch(strtolower($action))
				{
					case 'activate':
						$this->activate($checked);
						break;
					case 'deactivate':
						$this->deactivate($checked);
						break;
					case 'ban':
						$this->ban($checked);
						break;
					case 'delete':
						$this->delete($checked);
						break;
					case 'purge':
						$this->purge($checked);
						break;
				}
			}

			$where = array();
			$show_deleted = FALSE;

			// Filters
			$filter = $this->input->get('filter');
			switch($filter)
			{
				case 'inactive':
					$where['users.active'] = 0;
					break;
				case 'banned':
					$where['users.banned'] = 1;
					break;
				case 'deleted':
					$where['users.deleted'] = 1;
					$show_deleted = TRUE;
					break;
				case 'role':
					$role_id = (int)$this->input->get('role_id');
					$where['users.role_id'] = $role_id;

					foreach ($roles as $role)
					{
						if ($role->role_id == $role_id)
						{
							Template::set('filter_role', $role->role_name);
							break;
						}
					}
					break;

				default:
					$where['users.deleted'] = 0;
					$this->user_model->where('users.deleted', 0);
					break;
			}

			// First Letter
			$first_letter = $this->input->get('firstletter');
			if (!empty($first_letter))
			{
					$where['SUBSTRING( LOWER(username), 1, 1)='] = $first_letter;
			}

			$this->load->helper('ui/ui');

			$this->user_model->limit($this->limit, $offset)->where($where);
			$this->user_model->select('users.id, users.role_id, username, display_name, email, last_login, banned, users.deleted, role_name, active');

			Template::set('users', $this->user_model->find_all($show_deleted));

			// Pagination
			$this->load->library('pagination');

			$this->user_model->where($where);
			$total_users = $this->user_model->count_all();


			$this->pager['base_url'] = site_url(SITE_AREA .'/settings/users/index');
			$this->pager['total_rows'] = $total_users;
			$this->pager['per_page'] = $this->limit;
			$this->pager['uri_segment']	= 5;

			$this->pagination->initialize($this->pager);

			Template::set('current_url', current_url());
			Template::set('filter', $filter);

			Template::set('toolbar_title', lang('us_user_management'));
			Template::render();
	}

	//--------------------------------------------------------------------

	public function create()
	{
			$this->auth->restrict('Bonfire.Users.Add');

			$this->load->config('address');
			$this->load->helper('address');
			$this->load->helper('date');

			if ($this->input->post('submit'))
			{

					if ($id = $this->save_user())
					{
							$this->load->model('activities/Activity_model', 'activity_model');

							$user = $this->user_model->find($id);
							$log_name = (isset($user->display_name) && !empty($user->display_name)) ? $user->display_name : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
							$this->activity_model->log_activity($this->current_user->id, lang('us_log_create').' '. $user->role_name . ': '.$log_name, 'users');

							Template::set_message('User successfully created.', 'success');
							Template::redirect(SITE_AREA .'/settings/users');
					}

			}

			Template::set('roles', $this->role_model->select('role_id, role_name, default')->where('deleted', 0)->find_all());
			Template::set('languages', unserialize($this->settings_lib->item('site.languages')));

			Template::set('toolbar_title', lang('us_create_user'));
			Template::set_view('settings/user_form');
			Template::render();
	}

	//--------------------------------------------------------------------

	public function edit()
	{
			$this->auth->restrict('Bonfire.Users.Manage');

			$this->load->config('address');
			$this->load->helper('address');
			$this->load->helper('form');
			$this->load->helper('date');

			$user_id = $this->uri->segment(5);
			if (empty($user_id))
			{
					Template::set_message(lang('us_empty_id'), 'error');
					redirect(SITE_AREA .'/settings/users');
			}

			if ($this->input->post('submit'))
			{

					if ($this->save_user('update', $user_id))
					{
							$this->load->model('activities/Activity_model', 'activity_model');

							$user = $this->user_model->find($user_id);
							$log_name = (isset($user->display_name) && !empty($user->display_name)) ? $user->display_name : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
							$this->activity_model->log_activity($this->current_user->id, lang('us_log_edit') .': '.$log_name, 'users');

							Template::set_message('User successfully updated.', 'success');
					}

			}

			$user = $this->user_model->find($user_id);
			if (isset($user) && has_permission('Permissions.'.$user->role_name.'.Manage'))
			{
					Template::set('user', $user);
					Template::set('roles', $this->role_model->select('role_id, role_name, default')->find_all());
					Template::set('languages', unserialize($this->settings_lib->item('site.languages')));
					Template::set_view('settings/user_form');
			} else {
					Template::set_message(sprintf(lang('us_unauthorized'),$user->role_name), 'error');
					redirect(SITE_AREA .'/settings/users');
			}

			Template::set('toolbar_title', lang('us_edit_user'));

			Template::render();
	}

	//--------------------------------------------------------------------

	public function ban($users=false, $ban_message='')
	{

			if (!$users)
			{
					return;
			}

			$this->auth->restrict('Bonfire.Users.Manage');

			foreach ($users as $user_id)
			{
					$data = array(
																			'banned'		=> 1,
																			'ban_message'	=> $ban_message
																			);

					$this->user_model->update($user_id, $data);
			}
	}

	//--------------------------------------------------------------------

	public function delete($users)
	{

			if (empty($users))
			{
					$user_id = $this->uri->segment(5);

					if(!empty($user_id))
					{
							$users = array($user_id);
					}
			}

			if (!empty($users))
			{
					$this->auth->restrict('Bonfire.Users.Manage');

					foreach ($users as $id)
					{
							$user = $this->user_model->find($id);

							if (isset($user) && has_permission('Permissions.'.$user->role_name.'.Manage') && $user->id != $this->current_user->id)
							{
									if ($this->user_model->delete($id))
									{
											$this->load->model('activities/Activity_model', 'activity_model');

											$user = $this->user_model->find($id);
											$log_name = (isset($user->display_name) && !empty($user->display_name)) ? $user->display_name : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
											$this->activity_model->log_activity($this->current_user->id, lang('us_log_delete') . ': '.$log_name, 'users');
											Template::set_message(lang('us_action_deleted'), 'success');
									} else {
											Template::set_message(lang('us_action_not_deleted'). $this->user_model->error, 'error');
									}
							} else {
									if ($user->id == $this->current_user->id)
									{
											Template::set_message(lang('us_self_delete'), 'error');
									} else {
											Template::set_message(sprintf(lang('us_unauthorized'),$user->role_name), 'error');
									}
							}
					}
			} else {
					Template::set_message(lang('us_empty_id'), 'error');
			}

			redirect(SITE_AREA .'/settings/users');
	}

	//--------------------------------------------------------------------

	public function purge($users)
	{
		if (empty($users))
		{
			$user_id = $this->uri->segment(5);

			if(!empty($user_id))
			{
					$users = array($user_id);
			}
		}

		if (!empty($users) && is_array($users))
		{
			$this->auth->restrict('Bonfire.Users.Manage');

			foreach ($users as $id)
			{
				$this->user_model->delete($id, true);
			}
			Template::set_message(lang('us_action_purged'), 'success');
		}
		else {
			Template::set_message(lang('us_empty_id'), 'error');
		}

		Template::redirect(SITE_AREA .'/settings/users');
	}

	//--------------------------------------------------------------------

	public function restore()
	{
			$id = $this->uri->segment(5);

			if ($this->user_model->update($id, array('users.deleted'=>0)))
			{
				Template::set_message('User successfully restored.', 'success');
			}
			else
			{
				Template::set_message('Unable to restore user: '. $this->user_model->error, 'error');
			}

			Template::redirect(SITE_AREA .'/settings/users');
	}

	//--------------------------------------------------------------------


	//--------------------------------------------------------------------
	// !HMVC METHODS
	//--------------------------------------------------------------------

	public function access_logs($limit=15)
	{
			$logs = $this->user_model->get_access_logs($limit);

			return $this->load->view('settings/access_logs', array('access_logs' => $logs), true);
	}

	//--------------------------------------------------------------------



	//--------------------------------------------------------------------
	// !PRIVATE METHODS
	//--------------------------------------------------------------------

	public function unique_email($str)
	{
		if ($this->user_model->is_unique('email', $str))
		{
			return true;
		}
		else
		{
			$this->form_validation->set_message('unique_email', lang('us_email_in_use'));
			return false;
		}
	}

	//--------------------------------------------------------------------

	private function save_user($type='insert', $id=0)
	{

		if ($type == 'insert')
		{
			$this->form_validation->set_rules('email', lang('bf_email'), 'required|trim|unique[bf_users.email]|valid_email|max_length[120]|xss_clean');
			$this->form_validation->set_rules('password', lang('bf_password'), 'required|trim|strip_tags|min_length[8]|max_length[120]|valid_password|xss_clean');
			$this->form_validation->set_rules('pass_confirm', lang('bf_password_confirm'), 'required|trim|strip_tags|matches[password]|xss_clean');
		}
		else {
			$_POST['id'] = $id;
			$this->form_validation->set_rules('email', lang('us_label_email'), 'required|trim|valid_email|max_length[120]|xss_clean');
			$this->form_validation->set_rules('password', lang('bf_password'), 'trim|strip_tags|min_length[8]|max_length[120]|valid_password|matches[pass_confirm]|xss_clean');
			$this->form_validation->set_rules('pass_confirm', lang('bf_password_confirm'), 'trim|strip_tags|xss_clean');
		}

		$use_usernames = $this->settings_lib->item('auth.use_usernames');

		if ($use_usernames)
		{
			$extra_unique_rule = $type == 'update' ? ',bf_users.id' : '';

			$this->form_validation->set_rules('username', lang('bf_username'), 'required|trim|strip_tags|max_length[30]|unique[bf_users.username'.$extra_unique_rule.']|xsx_clean');
		}

		$this->form_validation->set_rules('display_name', lang('bf_display_name'), 'trim|strip_tags|max_length[255]|xss_clean');

		$this->form_validation->set_rules('language', lang('bf_language'), 'required|trim|strip_tags|xss_clean');
		$this->form_validation->set_rules('timezones', lang('bf_timezone'), 'required|trim|strip_tags|max_length[4]|xss_clean');
		$this->form_validation->set_rules('role_id', lang('us_role'), 'required|trim|strip_tags|max_length[2]|is_numeric|xss_clean');

		if ($this->form_validation->run() === false)
		{
			return false;
		}

		// Compile our core user elements to save.
		$data = array(
			'email'		=> $this->input->post('email'),
			'username'	=> $this->input->post('username'),
			'language'	=> $this->input->post('language'),
			'timezone'	=> $this->input->post('timezones'),
		);

		if ($this->input->post('password'))	$data['password'] = $this->input->post('password');
		if ($this->input->post('pass_confirm'))	$data['pass_confirm'] = $this->input->post('pass_confirm');
		if ($this->input->post('role_id')) $data['role_id'] = $this->input->post('role_id');
		if ($this->input->post('restore')) $data['deleted'] = 0;
		if ($this->input->post('unban')) $data['banned'] = 0;
		if ($this->input->post('display_name')) $data['display_name'] = $this->input->post('display_name');

		// Activation
		if ($this->input->post('activate'))
		{
			$data['active'] = 1;
		}
		else if ($this->input->post('deactivate'))
		{
			$data['active'] = 0;
		}

		if ($type == 'insert')
		{
			$return = $this->user_model->insert($data);
		}
		else	// Update
		{
			$return = $this->user_model->update($id, $data);
		}

		// Any modules needing to save data?
		Events::trigger('save_user', $this->input->post());

		return $return;
	}

	//--------------------------------------------------------------------

	//--------------------------------------------------------------------
	// ACTIVATION METHODS
	//--------------------------------------------------------------------
	/*
		Method:
			Activate()

		Activates selected users accounts.

		Parameters:
			$users 		- Array of User ID ints
	*/
	public function activate($users=false)
	{
		if (!$users)
		{
			return;
		}

		$this->auth->restrict('Bonfire.Users.Manage');
		foreach ($users as $user_id)
		{
			$this->user_status($user_id,1,0);
		}
	}

	//--------------------------------------------------------------------

	/*
			Method:
				deactivate()

			Deactivates selected users accounts.

			Parameters:
				$users 		- Array of User ID ints
		*/
	public function deactivate($users=false)
	{
		if (!$users)
		{
			return;
		}

		$this->auth->restrict('Bonfire.Users.Manage');

		foreach ($users as $user_id)
		{
			$this->user_status($user_id,0,0);
		}
	}

	//--------------------------------------------------------------------

	/*
			Method:
				User Status Update

			Activates or deavtivates a user from the users dashboard.
			Redirects to /settings/users on completion.

			Parameters:
				$user_id 		- User ID int
				$status			- 1 = Activate, -1 = Deactivate
				$supress_email	- 1 = Supress, All others = send email
	*/
	private function user_status($user_id = false, $status = 1, $supress_email = 0)
	{
		$supress_email = (isset($supress_email) && $supress_email == 1 ? true : false);

		if ($user_id !== false && $user_id != -1)
		{
			$result = false;
			$type = '';
			if ($status == 1)
			{
				$result = $this->user_model->admin_activation($user_id);
				$type = lang('bf_action_activate');
			}
			else
			{
				$result = $this->user_model->admin_deactivation($user_id);
				$type = lang('bf_action_deactivate');
			}

			$user = $this->user_model->find($user_id);
			$log_name = $this->settings_lib->item('auth.use_own_names') ? $this->current_user->username : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
			if (!isset($this->activity_model))
			{
				$this->load->model('activities/activity_model');
			}

			$this->activity_model->log_activity($this->current_user->id, lang('us_log_status_change') . ': '.$log_name . ' : '.$type."ed", 'users');
			if ($result)
			{
				$message = lang('us_active_status_changed');
				if (!$supress_email)
				{
					// Now send the email
					$this->load->library('emailer/emailer');

					$settings = $this->settings_lib->find_by('name','site.title');
					$data = array
					(
						'to'		=> $this->user_model->find($user_id)->email,
						'subject'	=> lang('us_account_active'),
						'message'	=> $this->load->view('_emails/activated', array('link'=>site_url(),'title'=>$settings['site.title']), true)
					);

					if ($this->emailer->send($data))
					{
						$message = lang('us_active_email_sent');
					}
					else
					{
						$message=lang('us_err_no_email'). $this->emailer->errors;
					}
				}
				Template::set_message($message, 'success');
			}
			else
			{
				Template::set_message(lang('us_err_status_error').$this->user_model->error,'error');
			} // END if
		}
		else
		{
			Template::set_message(lang('us_err_no_id'),'error');
		}
		Template::redirect(SITE_AREA.'/settings/users');
	}

	//--------------------------------------------------------------------
}

// End of Admin User Controller
/* End of file settings.php */
/* Location: ./application/core_modules/controllers/settings.php */