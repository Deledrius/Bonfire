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

class Settings extends Admin_Controller {

	//--------------------------------------------------------------------

	public function __construct()
	{
		parent::__construct();

		$this->auth->restrict('Site.Settings.View');

		Template::set('toolbar_title', 'Site Settings');

		$this->load->helper('config_file');
		$this->lang->load('settings');
	}

	//--------------------------------------------------------------------

	public function index()
	{
		if ($this->input->post('submit'))
		{
			if ($this->save_settings())
			{
				Template::set_message('Your settings were successfully saved.', 'success');
				redirect(SITE_AREA .'/settings');
			} else
			{
				Template::set_message('There was an error saving your settings.', 'error');
			}
		}
		// Read our current settings
		$settings = $this->settings_lib->find_all();
		Template::set('settings', $settings);

		// Get the possible languages
		$this->load->helper('translate/languages');
		Template::set('languages', list_languages());
		Template::set('selected_languages', unserialize($settings['site.languages']));

		Assets::add_module_js('settings', 'js/settings.js');

		Template::set_view('settings/settings/index');
		Template::render();
	}

	//--------------------------------------------------------------------

	//--------------------------------------------------------------------
	// !PRIVATE METHODS
	//--------------------------------------------------------------------

	private function save_settings()
	{
		$this->form_validation->set_rules('title', lang('bf_site_name'), 'required|trim|strip_tags|xss_clean');
		$this->form_validation->set_rules('system_email', lang('bf_site_email'), 'required|trim|strip_tags|valid_email|xss_clean');
		$this->form_validation->set_rules('list_limit','Items <em>p.p.</em>', 'required|trim|strip_tags|numeric|xss_clean');
		$this->form_validation->set_rules('password_min_length','Password Length', 'required|trim|strip_tags|numeric|xss_clean');
		$this->form_validation->set_rules('password_force_numbers', lang('bf_password_force_numbers'), 'trim|strip_tags|numeric|xss_clean');
		$this->form_validation->set_rules('password_force_symbols', lang('bf_password_force_symbols'), 'trim|strip_tags|numeric|xss_clean');
		$this->form_validation->set_rules('password_force_mixed_case', lang('bf_password_force_mixed_case'), 'trim|strip_tags|numeric|xss_clean');
		$this->form_validation->set_rules('languages[]', lang('bf_language'), 'required|trim|strip_tags|is_array|xss_clean');

		if ($this->form_validation->run() === false)
		{
			return false;
		}

		$data = array(
			array('name' => 'site.title', 'value' => $this->input->post('title')),
			array('name' => 'site.system_email', 'value' => $this->input->post('system_email')),
			array('name' => 'site.status', 'value' => $this->input->post('status')),
			array('name' => 'site.list_limit', 'value' => $this->input->post('list_limit')),

			array('name' => 'auth.allow_register', 'value' => isset($_POST['allow_register']) ? 1 : 0),
			array('name' => 'auth.user_activation_method', 'value' => isset($_POST['user_activation_method']) ? $_POST['user_activation_method'] : 0),
			array('name' => 'auth.login_type', 'value' => $this->input->post('login_type')),
			array('name' => 'auth.use_usernames', 'value' => isset($_POST['use_usernames']) ? $this->input->post('use_usernames') : 0),
			array('name' => 'auth.allow_remember', 'value' => isset($_POST['allow_remember']) ? 1 : 0),
			array('name' => 'auth.remember_length', 'value' => (int)$this->input->post('remember_length')),
			array('name' => 'auth.use_extended_profile', 'value' => isset($_POST['use_ext_profile']) ? 1 : 0),
			array('name' => 'auth.allow_name_change', 'value' => $this->input->post('allow_name_change') ? 1 : 0),
			array('name' => 'auth.name_change_frequency', 'value' => $this->input->post('name_change_frequency')),
			array('name' => 'auth.name_change_limit', 'value' => $this->input->post('name_change_limit')),
			array('name' => 'auth.password_min_length', 'value' => $this->input->post('password_min_length')),
			array('name' => 'auth.password_force_numbers', 'value' => $this->input->post('password_force_numbers')),
			array('name' => 'auth.password_force_symbols', 'value' => $this->input->post('password_force_symbols')),
			array('name' => 'auth.password_force_mixed_case', 'value' => $this->input->post('password_force_mixed_case')),

			array('name' => 'updates.do_check', 'value' => isset($_POST['do_check']) ? 1 : 0),
			array('name' => 'updates.bleeding_edge', 'value' => isset($_POST['bleeding_edge']) ? 1 : 0),
			array('name' => 'site.show_profiler', 'value' => isset($_POST['show_profiler']) ? 1 : 0),
			array('name' => 'site.show_front_profiler', 'value' => isset($_POST['show_front_profiler']) ? 1 : 0),
			array('name' => 'site.languages', 'value' => $this->input->post('languages') != '' ? serialize($this->input->post('languages')) : ''),


		);

		//destroy the saved update message in case they changed update preferences.
		if ($this->cache->get('update_message'))
		{
			$this->cache->delete('update_message');
		}

		// Log the activity
		$this->load->model('activities/Activity_model', 'activity_model');

		$this->activity_model->log_activity($this->current_user->id, lang('bf_act_settings_saved').': ' . $this->input->ip_address(), 'core');

		// save the settings to the DB
		$updated = $this->settings_model->update_batch($data, 'name');

		return $updated;
	}

	//--------------------------------------------------------------------
}
