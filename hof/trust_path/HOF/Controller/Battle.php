<?php

/**
 * @author bluelovers
 * @copyright 2012
 */

class HOF_Controller_Battle extends HOF_Class_Controller
{

	/**
	 * @var HOF_Class_User
	 */
	var $user;

	protected $_cache;

	function _init()
	{
		$this->user = &HOF::user();

		$this->options['escapeHtml'] = false;
	}

	function _main_before()
	{
		$this->user->LoadUserItem();
		$this->user->CharDataLoadAll();

		$this->_cache['lands'] = HOF_Model_Data::getLandAppear($this->user);
	}

	function _main_input()
	{

		$this->input->memory_party = HOF::$input->post->memory_party;

	}

	/**
	 * 狩場
	 */
	function _main_action_hunt()
	{
		$mapList = $this->_cache['lands'];

		$Union = array();

		/*
		if ($files = HOF_Class_File::glob(UNION))
		{
			foreach ($files as $file)
			{
				$UnionMons = HOF_Model_Char::newUnionFromFile($file);
				if ($UnionMons->is_Alive()) $Union[] = $UnionMons;
			}
		}

		if ($Union)
		{

			$result = $this->user->CanUnionBattle();

			if ($result !== true)
			{
				$left_minute = floor($result / 60);
				$left_second = $result % 60;
			}

			ob_start();
			HOF_Class_Char_View::ShowCharacters($Union);
			$union_showchar = ob_get_clean();

		}
		*/

		$logs = array();

		$log = HOF_Class_File::glob(LOG_BATTLE_UNION);
		foreach (array_reverse($log) as $file)
		{
			$limit++;

			$logs[] = HOF_Model_Data::getLogBattleFile($file);

			if (15 <= $limit) break;
		}

		$this->output->maps = $mapList;

		$this->output->union = $Union;
		$this->output->union_showchar = $union_showchar;

		$this->output->result = $result;
		$this->output->left_minute = $left_minute;
		$this->output->left_second = $left_second;

		$this->output->logs = $logs;

		$this->user->fpclose_all();
	}

	/**
	 * 一般モンスター
	 */
	function _main_action_common()
	{
		$this->input->monster_battle = HOF::$input->post->monster_battle;
		$this->input->common = HOF::$input->request['common'];

		$this->output['battle.target.id'] = $this->input->common;
		$this->output['battle.target.from.action'] = INDEX . '?common=' . $this->output['battle.target.id'];

		if ($this->_check_land())
		{
			$land_data = HOF_Model_Data::getLandInfo($this->input->common);
			$land = $land_data['land'];

			ob_start();
			$this->_cache['MonsterBattle'] = $this->MonsterBattle();
			$this->output->battle_show = ob_get_clean();

			if ($this->_cache['MonsterBattle'])
			{
				$this->user->SaveData();
			}
			else
			{
				$monster_list = $land_data['monster'];

				foreach ($monster_list as $id => $val)
				{
					if ($val[1]) $monster[] = HOF_Model_Char::newMon($id);
				}

				ob_start();

				HOF_Class_Char_View::ShowCharacters($monster, "MONSTER", $land["land"]);

				$this->output->monster_show = ob_get_clean();
			}

			$this->output->land = $land;
		}

		$this->output->monster_battle = $this->_cache['MonsterBattle'];

		$this->user->fpclose_all();
	}

	function _main_action_simulate()
	{
		$this->input->monster_battle = HOF::$input->post->monster_battle;

		$this->output->land['name'] = '模擬戦';
		$this->output['battle.target.from.action'] = INDEX . '?simulate';
	}

	function _main_action_union()
	{
		if ($this->user->CanUnionBattle() !== true)
		{
			$host = $_SERVER['HTTP_HOST'];
			$uri = rtrim(dirname($_SERVER['PHP_SELF']));
			$extra = INDEX;
			header("Location: http://$host$uri/$extra?hunt");
			exit;
		}

		$this->_cache['union'] = HOF_Model_Char::newUnionFromFile();

		$this->input->monster_battle = HOF::$input->post->monster_battle;
		$this->input->union = HOF::$input->request->union;

		$this->output->land['name'] = 'Union Monster';
		$this->output['battle.target.from.action'] = INDEX . '?union=' . $_GET["union"];
	}

	function _check_union()
	{
		// 倒されているか、存在しない場合。
		if (!$this->_cache['union']->UnionNumber($this->input->union) || !$this->_cache['union']->is_Alive())
		{
			$this->_error("Defeated or not Exists.");
			return false;
		}

		return true;
	}

	function _union()
	{
		if ($this->_check_union())
		{
			if ($this->output->monster_battle = $this->UnionProcess())
			{
				// 戦闘する
				$this->user->SaveData();
			}
			else
			{
				// 表示
				$this->UnionShow();
			}
		}

		$this->user->fpclose_all();
	}

	//	Unionモンスターの処理
	function UnionProcess()
	{
		if (!$this->input->monster_battle) return false;

		$Union = $this->_cache['union'];

		// ユニオンモンスターのデータ
		$UnionMob = HOF_Model_Char::getBaseMonster($Union->MonsterNumber);
		$this->MemorizeParty(); //パーティー記憶
		// 自分パーティー
		foreach ($this->user->char as $key => $val)
		{ //チェックされたやつリスト
			if (HOF::$input->post["char_" . $key])
			{
				$MyParty[] = $this->user->char[$key];
				$TotalLevel += $this->user->char[$key]->level; //自分PTの合計レベル
			}
		}
		// 合計レベル制限
		if ($UnionMob["LevelLimit"] < $TotalLevel)
		{
			$this->_error('合計レベルオーバー(' . $TotalLevel . '/' . $UnionMob["LevelLimit"] . ')', "margin15");
			return false;
		}
		if (count($MyParty) === 0)
		{
			$this->_error('戦闘するには最低1人必要', "margin15");
			return false;
		}
		else
			if (5 < count($MyParty))
			{
				$this->_error('戦闘に出せるキャラは5人まで', "margin15");
				return false;
			}
		if (!$this->user->WasteTime(UNION_BATTLE_TIME))
		{
			$this->_error('Time Shortage.', "margin15");
			return false;
		}

		// 敵PT数

		// ランダム敵パーティー
		if ($UnionMob["SlaveAmount"]) $EneNum = $UnionMob["SlaveAmount"] + 1; //PTメンバと同じ数だけ。
		else  $EneNum = 5; // Union含めて5に固定する。

		if ($UnionMob["SlaveSpecify"]) $EnemyParty = $this->EnemyParty($EneNum - 1, $Union->Slave, $UnionMob["SlaveSpecify"]);
		else  $EnemyParty = $this->EnemyParty($EneNum - 1, $Union->Slave, $UnionMob["SlaveSpecify"]);

		// unionMobを配列のおよそ中央に入れる
		$EnemyParty->insert(floor(count($EnemyParty) / 2), array($Union));

		$this->user->UnionSetTime();

		$battle = new HOF_Class_Battle($MyParty, $EnemyParty);
		$battle->SetUnionBattle();
		$battle->SetBackGround($Union->UnionLand); //背景
		//$battle->SetTeamName($this->user->name,"Union:".$Union->Name());
		$battle->SetTeamName($this->user->name, $UnionMob["UnionName"]);
		$battle->Process(); //戦闘開始

		$battle->SaveCharacters(); //キャラデータ保存
		list($UserMoney) = $battle->ReturnMoney(); //戦闘で得た合計金額
		$this->user->GetMoney($UserMoney); //お金を増やす
		$battle->RecordLog("UNION");
		// アイテムを受け取る
		if ($itemdrop = $battle->ReturnItemGet(0))
		{
			$this->user->LoadUserItem();
			foreach ($itemdrop as $itemno => $amount) $this->user->AddItem($itemno, $amount);
			$this->user->SaveUserItem();
		}

		return true;
	}

	//	Unionモンスターの表示
	function UnionShow()
	{
		$Union = $this->_cache['union'];

		HOF_Class_Char_View::ShowCharacters(array($Union), false, "sea");
	}

	function _simulate()
	{
		if ($this->_cache['Process'] = $this->SimuBattleProcess())
		{
			$this->user->SaveData();
		}

		$this->user->fpclose_all();
	}

	function SimuBattleProcess()
	{
		if ($this->input->monster_battle)
		{
			$this->MemorizeParty(); //パーティー記憶
			// 自分パーティー
			foreach ($this->user->char as $key => $val)
			{
				//チェックされたやつリスト
				if (HOF::$input->post["char_" . $key]) $MyParty[] = $this->user->char[$key];
			}
			if (count($MyParty) === 0)
			{
				$this->_error('戦闘するには最低1人必要', "margin15");
				return false;
			}
			else
			{
				if (5 < count($MyParty))
				{
					$this->_error('戦闘に出せるキャラは5人まで', "margin15");
					return false;
				}
			}
			HOF_Helper_Battle::DoppelBattle($MyParty, 50);
			return true;
		}
	}

	function _error($s, $a = null)
	{
		$this->output->error[] = array($s, $a);
		$this->error[] = $s;
	}

	/**
	 * 戦闘時に選択したメンバーを記憶する
	 */
	function MemorizeParty()
	{
		if ($this->input->memory_party)
		{
			//$temp	= $this->party_memo;//一時的に記憶
			//$this->party_memo	= array();
			foreach ($this->user->char as $key => $val)
			{ //チェックされたやつリスト
				if ($_POST["char_" . $key]) //$this->party_memo[]	 = $key;
 						$PartyMemo[] = $key;
			}
			//if(5 < count($this->party_memo) )//5人以上は駄目
			//	$this->party_memo	= $temp;
			if (0 < count($PartyMemo) && count($PartyMemo) < 6)
			{
				/*
				$this->party_memo = implode("<>", $PartyMemo);
				*/
				$this->user->party_memo = $PartyMemo;
			}
		}
	}

	/**
	 * モンスターとの戦闘
	 */
	function MonsterBattle()
	{
		if ($this->input->monster_battle)
		{
			$this->MemorizeParty(); //パーティー記憶
			// そのマップで戦えるかどうか確認する。

			$land = $this->_cache['lands'];

			// Timeが足りてるかどうか確認する
			if ($this->user->time < NORMAL_BATTLE_TIME)
			{
				$this->_error("Time 不足 (必要 Time:" . NORMAL_BATTLE_TIME . ")", "margin15");
				return false;
			}

			// bluelovers
			$MyParty = array();
			// bluelovers

			// 自分パーティー
			foreach ($this->user->char as $key => $val)
			{ //チェックされたやつリスト
				if (HOF::$input->post["char_" . $key]) $MyParty[] = $this->user->char[$key];
			}

			if (count($MyParty) === 0)
			{
				$this->_error('戦闘するには最低1人必要', "margin15");
				return false;
			}
			else
			{
				if (5 < count($MyParty))
				{
					$this->_error('戦闘に出せるキャラは5人まで', "margin15");
					return false;
				}
			}

			// bluelovers
			$MyParty = HOF_Class_Battle_Team::newInstance($MyParty);
			// bluelovers

			// 敵パーティー(または一匹)

			//	include (DATA_MONSTER);
			/*
			list($Land, $MonsterList) = HOF_Model_Data::getLandInfo($this->input->common);
			*/

			$land_data = HOF_Model_Data::getLandInfo($this->input->common);

			$Land = $land_data['land'];
			$MonsterList = $land_data['monster'];

			$EneNum = $this->EnemyNumber($MyParty);
			$EnemyParty = $this->EnemyParty($EneNum, $MonsterList);

			$this->user->WasteTime(NORMAL_BATTLE_TIME); //時間の消費

			$battle = new HOF_Class_Battle($MyParty, $EnemyParty);

			$battle->SetBackGround($Land["land"]); //背景
			$battle->SetTeamName($this->user->name, $Land["name"]);
			$battle->Process(); //戦闘開始
			$battle->SaveCharacters(); //キャラデータ保存
			list($UserMoney) = $battle->ReturnMoney(); //戦闘で得た合計金額
			//お金を増やす
			$this->user->GetMoney($UserMoney);
			//戦闘ログの保存
			if ($this->user->record_btl_log) $battle->RecordLog();

			// アイテムを受け取る
			if ($itemdrop = $battle->ReturnItemGet(0))
			{
				$this->user->LoadUserItem();
				foreach ($itemdrop as $itemno => $amount) $this->user->AddItem($itemno, $amount);
				$this->user->SaveUserItem();
			}

			//dump($itemdrop);
			//dump($this->user->item);
			return true;
		}
	}

	/**
	 * まだ行けないマップなのに行こうとした。
	 */
	function _check_land()
	{
		if (!array_key_exists($this->input->common, $this->_cache['lands']))
		{
			$this->_error('マップが出現して無い (not appeared or not exist)', 'margin15');

			return false;
		}

		return true;
	}

	/**
	 * 敵の数を返す	数～数+2(max:5)
	 */
	function EnemyNumber($party)
	{
		$min = count($party); //プレイヤーのPT数
		if ($min == 5) //5人なら5匹
 				return 5;
		$max = $min + ENEMY_INCREASE; // つまり、+2なら[1人:1～3匹] [2人:2～4匹] [3:3-5] [4:4-5] [5:5]
		if ($max > 5) $max = 5;
		mt_srand();
		return mt_rand($min, $max);
	}

	/**
	 * 敵のPTを作成、返す
	 * Specify=敵指定(配列)
	 */
	function EnemyParty($Amount, $MonsterList, $Specify = false)
	{

		// 指定モンスター
		if ($Specify)
		{
			$MonsterNumbers = $Specify;
		}

		// モンスターをとりあえず配列に全部入れる
		$enemy = array();

		if (!$Amount) return $enemy;
		mt_srand();
		for ($i = 0; $i < $Amount; $i++) $MonsterNumbers[] = $this->SelectMonster($MonsterList);

		// 重複しているモンスターを調べる
		$overlap = array_count_values($MonsterNumbers);

		// 敵情報を読んで配列に入れる。
		foreach ($MonsterNumbers as $Number)
		{
			/*
			if (1 < $overlap[$Number]) //1匹以上出現するなら名前に記号をつける。
			$enemy[] = new monster(HOF_Model_Char::getBaseMonster($Number, true));
			else  $enemy[] = new monster(HOF_Model_Char::getBaseMonster($Number));
			*/

			$enemy[] = HOF_Model_Char::newMon($Number, (1 < $overlap[$Number]));
		}

		$enemy = HOF_Class_Battle_Team::newInstance($enemy);

		return $enemy;
	}

	/**
	 * 出現する確率から敵を選んで返す
	 */
	function SelectMonster($monster)
	{
		foreach ($monster as $val) $max += $val[0]; //確率の合計
		$pos = mt_rand(0, $max); //0～合計 の中で乱数を取る
		foreach ($monster as $monster_no => $val)
		{
			$upp += $val[0]; //その時点での確率の合計
			if ($pos <= $upp) //合計より低ければ　敵が決定される
 					return $monster_no;
		}
	}

}


?>