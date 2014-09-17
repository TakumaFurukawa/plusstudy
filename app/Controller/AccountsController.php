<?php
App::uses('AppController', 'Controller');
/**
 * Accounts Controller
 *
 * @property Account $Account
 * @property PaginatorComponent $Paginator
 */
class AccountsController extends AppController {

/**
 * Components
 *
 * @var array
 */
	public $uses = array('Account', 'Seminar', 'Participant', 'TeachMe', 'NewaccTmp');
	public $components = array('Paginator', 'MyAuth');

/**
 * beforeFilter method
 *
 * @return void
 */
	public function beforeFilter() {
			// 認証済みかどうか調べる
			$this->MyAuth->isAuth($this);
		}

/**
 * index method
 * ログインページ
 * @return void
 */
	public function index() {
		// ページタイトル設定
		$this->set('title_for_layout', 'PlusStudy ログイン');
		$msg = '';

	   	if($this->Session->check('Auth')) {
			// ログイン済み
			return $this->redirect(array('action' => 'top'));
		}

		if ($this->request->is('post')) {
			$options = array(
				'conditions' => array(
						'Account.mailaddress' => $this->request->data('Account.mailaddress'),
						'Account.passwd' => $this->request->data('Account.passwd')
					)
			);
			if($this->Account->find('count', $options) === 1) {

				// セッションにIDを格納
				$account = $this->Account->find('first', $options);
				$this->Session->write('Auth.id', $account['Account']['id']);
				return $this->redirect(array('action' => 'top'));
			}
			else {

				$msg = 'ユーザ名またはパスワードが間違っています';

				/* セッションテスト
				$this->Session->write('backdata', $this->request->data);
				$backdata = $this->Session->read('backdata');
				$this->request->data['Account'] = $backdata['Account'];
				*/
			}
		}

		$this->set('msg', $msg);
	}

/**
 * top method
 * トップページ
 * @return void
 */
	public function top() {
		// ページタイトル設定
		$this->set('title_for_layout', 'PlusStudy');
		$msg = '';
		if($this->request->is('post')) {
			// ログアウト
			$this->Session->delete('Auth');
			return $this->redirect(array('action' => 'index'));
		}

		if($this->Session->check('Auth')) {
			// セッションのIDを元にデータを取得する
			$options = array(
				'conditions' => array(
						'Account.' . $this->Account->primaryKey => $this->Session->read('Auth.id')
					)
			);
			$account = $this->Account->find('first', $options);
			$msg = 'こんにちは、' . $account['Account']['last_name'] . $account['Account']['first_name'] . 'さん！';
		}

		$this->set("msg", $msg);

		// ニーズ一覧を取得
		$this->set('teachmes', $this->TeachMe->find('all'));

		// セミナー一覧を取得
		$this->set('seminars', $this->Seminar->find('all'));

		// 参加申請している勉強会を取得
		$options = array(
			'conditions' => array(
					'Participant.account_id' => $this->Session->read('Auth.id')
				)
		);
		$participants = $this->Participant->find('all', $options);

		// 参加申請している勉強会の中に終わっているものがあるか調べる
		$dt = new DateTime();
		$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'));
		$today = $dt->format('Y-m-d');
		foreach($participants as $participant) {
			if(strtotime($participant['Seminar']['end']) < strtotime($today)) {
				// セッション作成
				$this->Session->write('participant', $participant);

				// フィードバックページへリダイレクト
				return $this->redirect(array('controller' => 'Seminars',   'action' => 'feedback'));
			}
		}
	}

/**
 * profile method
 * プロフィールページ
 * @return void
 */
	public function profile() {

		// 指定されたIDを元にアカウント情報を取得
		$id = $this->params['url']['id'];
		$options = array('conditions' => array('Account.' . $this->Account->primaryKey => $id));
		$account = $this->Account->find('first', $options);

		// データが見つからなかったらトップページへリダイレクト
		if(count($account) === 0) {
			return $this->redirect(array('controller' => 'Accounts', 'action' => 'index'));
		}

		// データをViewに渡す
		$this->set('account', $account);

		// ページタイトル設定
		$this->set('title_for_layout', 'プロフィール - ' . $account['Account']['last_name'] . $account['Account']['first_name']);

		// その人が主催している勉強会の情報を取得する
		$options = array(
			'conditions' => array(
					'Seminar.account_id' => $id
				)
		);
		$this->set('myseminars', $this->Seminar->find('all', $options));

		// その人が参加予定の勉強会のIDを取得する
		$options = array(
			'conditions' => array(
					'Participant.account_id' => $id
				)
		);
		$participants = $this->Participant->find('all', $options);

		// 参加予定のIDを元に勉強会の情報を取得する
		$partseminars = array();
		foreach($participants as $participant) {
			$options = array(
				'conditions' => array(
					'Seminar.id' => $participant['Participant']['seminar_id']
				)
			);
			$partseminars[] = $this->Seminar->find('first', $options);
		}
		$this->set('partseminars', $partseminars);
	}


	/**
	 * startNewAcc method
	 * 新規アカウント登録メールアドレス入力ページ
	 * @throws NotFoundException
	 * @param int $id
	 * @return void
	 */
	public function startNewAcc() {
		$this->set('title_for_layout', '新規アカウント登録');

		$msg = '';

		if ($this->referer() === ROOT_URL . $this->name . '/' . $this->action ||
			  $this->referer() === ROOT_URL . $this->name . '/' . $this->action . '/') {

			if (empty($this->request->data['Account']['mailaddress']))
				$msg = 'メールアドレスが入力されていません';
			else if (!preg_match('/^.+@.+$/', $this->request->data['Account']['mailaddress']))
				$msg = '正しいメールアドレスを入力してください';
			else {
				//--- 正しくメールアドレスが入力されていた場合 ---

				// すでにそのアドレスが登録されていないか確認
				if ($this->Account->find('count', array(
						'conditions' => array('Account.mailaddress' => $this->request->data['Account']['mailaddress']),
					)) > 0) {
					// すでにそのアドレスは使われている
					$msg = '既にそのアドレスは使われています';
				} else {
					$this->Session->write('NewAcc.mailaddress', $this->request->data['Account']['mailaddress']);
					$this->redirect(array('action' => 'sentMail'));
				}
			}

		}

		$this->set(array(
			'msg' => $msg,
		));
	}


	/**
	 * sentMail method
	 * 登録メールアドレス確認メール送信完了ページ
	 * @throws NotFoundException
	 * @return void
	 */
	public function sentMail() {
		$this->set('title_for_layout', '新規アカウント登録 | メールアドレス送信完了');

		if (!$this->Session->check('NewAcc.mailaddress')) {
			echo 'メールアドレス送信完了';
		}

		// 仮登録ワンタイムパスワード
		$passwd = mt_rand(0, getrandmax());

		//----- 仮登録処理 -----
		// 既に仮登録がすんでいるか確認
		$data = array(
			'NewaccTmp' => array(
				'mailaddress' => $this->Session->read('NewAcc.mailaddress'),
				'passwd' => $passwd,
			),
		);
		$this->NewaccTmp->save($data);
		// $this->Session->delete('NewAcc.mailaddress');

		// 本登録用URL整形
		$url = ROOT_URL . 'Accounts/input/' . $passwd . '/';

		//----- パラメタセット -----
		$this->set(array(
			'url' => $url,
		));

	}


	/**
	 * input method
	 * 会員情報入力ページ
	 * @throws NotFoundException
	 * @param string $passwd
	 * @return void
	 */
	public function input( $passwd = null ) {

		// 不正アクセス時に強制遷移
		if ($passwd === null && !$this->Session->check('NewAcc1Pass')) $this->redirect(array('action' => 'index'));

		// 仮登録ワンパスワードを格納
		if ($passwd !== null)
			$this->Session->write('NewAcc1Pass', $passwd);

		// メールアドレス取得
		if ($this->Session->check('NewAcc.mailaddress'))
			$this->request->data['Account']['mailaddress'] = $this->Session->read('NewAcc.mailaddress');


		//----- バリデーションチェック -----
		$validFlg = true;

		// エラーメッセージ
		$msgName = '';						// 漢字氏名
		$msgNameKana = '';				// カナ氏名
		$msgCourse = '';					// コース
		$msgGrade = '';						// 学年
		$msgSubject = '';					// 学科
		$msgPR = '';							// 自己PR
		$msgPasswd = '';					// パスワード


		if ($this->referer() === ROOT_URL . $this->name . '/' . $this->action ||
			  $this->referer() === ROOT_URL . $this->name . '/' . $this->action . '/' ||
			  $this->referer() === ROOT_URL . $this->name . '/' . $this->action . '/' . $passwd . '/') {

			// 名前 ---------------------------------
			if (empty($this->request->data['Account']['last_name']) || empty($this->request->data['Account']['first_name'])) {

				$msgName = '氏名が入力されていません';
				$validFlg = false;

			} else if (!preg_match('/^.+$/', $this->request->data['Account']['last_name']) || !preg_match('/^.+$/', $this->request->data['Account']['first_name'])) {

				$msgName = '氏名に記号は使えません';
				$validFlg = false;

			}

			// ナマエ ---------------------------------
			if (empty($this->request->data['Account']['last_ruby']) || empty($this->request->data['Account']['first_ruby'])) {

				$msgNameKana = '氏名（カナ）が入力されていません';
				$validFlg = false;

			} else if (!preg_match('/^[ァ-ヾ]+$/u', $this->request->data['Account']['last_ruby']) || !preg_match('/^[ァ-ヾ]+$/u', $this->request->data['Account']['first_ruby'])) {

				$msgNameKana = '全角カタカナ以外入力できません';
				$validFlg = false;

			}

			// パスワード ---------------------------------
			if (empty($this->request->data['Account']['passwd']) || empty($_POST['confirm'])) {

				$msgPasswd = 'パスワードが入力されていません';
				$validFlg = false;

			} else if (!preg_match('/^.{8,20}$/', $this->request->data['Account']['passwd'])) {

				$msgPasswd = '8文字以上20文字未満の半角英数で入力ください';
				$validFlg = false;

			} else if ($this->request->data['Account']['passwd'] !== $_POST['confirm']) {

				$msgPasswd = 'パスワードが一致しません';
				$validFlg = false;
			}

			// コース ---------------------------------
			if (empty($this->request->data['Account']['course'])) {
				$msgCourse = 'コースを選択してください';
				$validFlg = false;
			}

			// 学年 ---------------------------------
			if ($this->request->data['Account']['grade'] === '') {

				$msgGrade = '学年が入力されていません';
				$validFlg = false;

			} else if (+$this->request->data['Account']['grade'] === 0) {

				$msgGrade = '正しい学年を入力してください';
				$validFlg = false;

			} else if (!preg_match('/^[1-9]+[0-9]*$/', $this->request->data['Account']['grade'])) {

				$msgGrade = '半角数字でご入力ください';
				$validFlg = false;

			}

			// 学科 ---------------------------------
			if (empty($this->request->data['Account']['subject'])) {

				$msgSubject = '学科が入力されていません';
				$validFlg = false;

			}

			// 自己PR ---------------------------------
			if (mb_strlen($this->request->data['Account']['description']) > 200) {

				$msgPR = '文字数が多すぎます（200字以内）';
				$validFlg = false;

			}


			if ($validFlg) {

				//--- 遷移 ---
				$this->Session->write('NewAcc', $this->request->data['Account']);
				$this->redirect(array('action' => 'inputConfirm'));
			}
		}



		//----- 戻るボタンで戻ってきた時の処理 -----
		if ($this->referer() === ROOT_URL . $this->name . '/inputConfirm' ||
			  $this->referer() === ROOT_URL . $this->name . '/inputConfirm/') {
			$this->request->data['Account'] = $this->Session->read('NewAcc');
		}



		$this->set(array(
			'msgName' => $msgName,
			'msgNameKana' => $msgNameKana,
			'msgCourse' => $msgCourse,
			'msgGrade' => $msgGrade,
			'msgSubject' => $msgSubject,
			'msgPR' => $msgPR,
			'msgPasswd' => $msgPasswd,
		));

	}


	/**
	 * inputConfirm method
	 * 入力情報確認ページ
	 * @throws NotFoundException
	 * @return void
	 */
	public function inputConfirm() {
		if (!$this->Session->check('NewAcc')) $this->redirect(array('action' => 'index'));

		$this->set('acc', $this->Session->read('NewAcc'));
	}


	/**
	 * inputComp method
	 * アカウント本登録完了ページ
	 * @throws NotFoundException
	 * @return void
	 */
	public function inputComp() {
		if (!$this->Session->check('NewAcc') && $this->Session->check('NewAcc1Pass')) $this->redirect(array('action' => 'index'));

		//----- 本登録処理 -----
		$data = array('Account' => $this->Session->read('NewAcc'));
		if ($this->Account->save($data)) {
			//----- 成功 -----

			// 仮登録レコード削除
			$query = "DELETE FROM newacc_tmps WHERE passwd = " . $this->Session->read('NewAcc1Pass');
			$query .= " AND mailaddress = '" . $this->Session->read('NewAcc.mailaddress') ."'";
			$this->NewaccTmp->query($query);

			// 新規アカウント登録セッション削除
			$this->Session->delete('NewAcc');
			$this->Session->delete('NewAcc1Pass');
		}
	}

}