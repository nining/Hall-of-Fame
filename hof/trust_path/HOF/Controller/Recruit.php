<?php

/**
 * @author bluelovers
 * @copyright 2012
 */

class HOF_Controller_Recruit extends HOF_Class_Controller
{

	/**
	 * @var HOF_Class_User
	 */
	var $user;

	function _init()
	{
		$this->user = &HOF::user();

		/*
		$this->char_recruit_money = array(
			1 => 2000,
			2 => 2000,
			3 => 2500,
			4 => 4000,
			);
		*/

		$this->output->error = array();
		//$this->output->char_recruit_money = $this->char_recruit_money;
	}

	function _main_before()
	{
		$this->_input();
	}

	function _input()
	{
		$this->input->recruit = $_POST['recruit'];
		$this->input->recruit_name = $_POST['recruit_name'];
		$this->input->recruit_no = max(0, intval($_POST['recruit_no']));

		if (isset($_POST['recruit_gend']))
		{
			$this->input->recruit_gend = HOF_Helper_Math::minmax($_POST['recruit_gend'], GENDER_UNKNOW, GENDER_GIRL);
		}
		else
		{
			$this->input->recruit_gend = null;
		}
	}

	function _recruit()
	{
		$this->RecruitShow();

		if ($this->RecruitProcess()) $this->user->SaveData();

		$this->output->error_max = (MAX_CHAR <= $this->user->CharCount());

		$this->user->fpclose_all();
	}

	function _error($s, $a = null)
	{
		$this->output->error[] = array($s, $a);
		$this->error[] = $s;
	}

	function RecruitProcess()
	{

		// 雇用数限界
		if (MAX_CHAR <= count($this->user->char)) return false;

		if ($this->input->recruit)
		{

			// 名前処理
			if ($this->input->recruit_name)
			{
				if (is_numeric(strpos($this->input->recruit_name, "\t"))) return "error.";

				$name = $this->input->recruit_name;

				$name = stripslashes($name);

				$name = preg_replace('/^["\'\s\t\r\n]+|[\s\t\r\n"\']+$/', '', $name);
				$name = preg_replace('/\s\s+?/', ' ', $name);

				$len = strlen($name);

				if (0 == $len || 16 < $len)
				{
					$this->_error("名前が短すぎるか長すぎです", "margin15");
					return false;
				}

				$name = htmlspecialchars($name, ENT_QUOTES);
			}
			else
			{
				$this->_error("名前が空欄です", "margin15");
				return false;
			}

			$_exists_chars_ = $this->user->_cache_user_()->data('char_list');

			if (in_array($name, (array)$_exists_chars_))
			{
				$this->_error("This Char ID < $name > has been already used.", "margin15");
				return false;
			}

			$char = null;

			if ($this->input->recruit_no && $this->_cache['chars'][$this->input->recruit_no] instanceof HOF_Class_Char)
			{

				$job = $this->_cache['chars'][$this->input->recruit_no]->job;
				$gender = $this->_cache['chars'][$this->input->recruit_no]->gender;

				$char = HOF_Model_Char::newBaseChar($job, array("name" => $name, "gender" => $gender));
			}
			else
			{
				$this->_error('Select characters job.');
				return false;
			}

			// キャラのタイプ
			$hire = $char->recruit_money;

			//雇用金
			if ($hire <= $this->user->money)
			{
				$this->user->TakeMoney($hire);
			}
			else
			{
				$this->_error("お金が足りません", "margin15");
				return false;
			}

			// キャラを保存する
			$char->SaveCharData($this->user->id);

			$_exists_chars_[$char->birth] = $char->name;

			$this->user->_cache_user_()->data('char_list', $_exists_chars_);

			HOF_Helper_Global::ShowResult($char->Name() . "($char->job_name) が仲間になった！", "margin15");
			return true;
		}
	}

	function RecruitShow()
	{

		$this->output->error_max = (MAX_CHAR <= $this->user->CharCount());

		if (!$this->output->error_max)
		{

			$chars = array();
			$k = 1;

			$base_list = HOF_Model_Char::getBaseCharList();

			foreach ($base_list as $i)
			{
				$base = HOF_Model_Char::getBaseCharStatus($i);
				$jobdata = HOF_Model_Data::getJobData($i);

				foreach(array_keys($jobdata['gender']) as $j)
				{
					$chars[$k] = HOF_Model_Char::newBaseChar($i, array('gender' => $j));

					if ($j == GENDER_GIRL)
					{
						$Gender = '♀';
					}
					elseif ($j == GENDER_BOY)
					{
						$Gender = '♂';
					}
					else
					{
						$Gender = '';
					}

					$chars[$k]->job_name .= $Gender;

					$chars[$k]->recruit_money = $base['data_ex']['recruit_money'];

					$k++;
				}
			}

			$this->_cache['chars'] = $chars;

			$this->output->char_recruit = array();

			$k = 0;
			foreach ($chars as $i => $char)
			{
				$this->output->char_recruit[$k][$i] = $char;

				if (!($i % 4)) $k++;
			}
		}

	}
}


?>