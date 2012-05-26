<?php

/**
 * @author bluelovers
 * @copyright 2012
 */

class HOF_Class_Main extends HOF_Class_User
{
	var $islogin = false;

	protected static $instance;

	function __construct()
	{
		if (!isset(self::$instance))
		{
			self::$instance = $this;
		}
		else
		{
			die('error!!');
		}

		$this->session();

		$this->Set_ID_PASS();
	}

	public static function &getInstance()
	{
		if (!isset(self::$instance))
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @return HOF_Class_Session_User
	 */
	function &session()
	{
		return HOF_Class_Session_User::getInstance();
	}

	/**
	 * pass と id を設定する
	 */
	function Set_ID_PASS()
	{
		$id = HOF::$input->post->id;

		if ($id)
		{
			$this->id = $id; //$_POST["id"];
			// ↓ログイン処理した時だけ
			if (HOF_Controller_Game::is_registered($_POST["id"]))
			{
				$this->session()->id($this->id);
			}
		}
		elseif ($this->session()->id())
		{
			$this->id = $this->session()->id();
		}

		if (!$this->id || !$uniqid = HOF_Model_Main::user_get_uuid($this->id))
		{
			unset($this->pass);

			return false;
		}

		$pass = HOF::$input->post->pass;

		if ($pass)
		{
			/*
			$this->pass = HOF_Helper_Char::CryptPassword($pass); //$_POST["pass"];
			*/

			$this->pass = HOF_Model_Main::user_pass_encode($this->id, $pass);
		}
		elseif ($this->session()->pass())
		{
			$this->pass = $this->session()->pass();
		}

		//if ($this->pass) $this->pass = HOF_Helper_Char::CryptPassword($this->pass);
	}

	/**
	 * ログインした時間を設定する
	 */
	function RenewLoginTime()
	{
		$this->timestamp['login'] = time();
	}

	function allowPlay()
	{
		static $flag;

		if (!isset($flag) || $over)
		{
			$flag = ($this->CheckLogin() && !$this->FirstLogin()) ? true : false;
		}

		return $flag;
	}

	function FirstLogin($over = false)
	{
		static $flag;

		if (!isset($flag) || $over)
		{
			$flag = !HOF_Class_Controller::getInstance('game', __FUNCTION__ )->main()->_main_stop();
		}

		return $flag;
	}

	/**
	 * ログインしたのか、しているのか、ログアウトしたのか。
	 */
	function CheckLogin($over = false)
	{
		static $flag;

		if (!isset($flag) || $over)
		{
			HOF_Class_Controller::getInstance('game', __FUNCTION__ )->main()->_main_stop();

			$flag = $this->islogin;
		}

		return $flag;
	}

}
