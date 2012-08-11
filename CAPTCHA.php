<?php
/*
* お問い合せ画面にCAPTCHAを追加します
* Copyright (C) 2012 Nobuhiko Kimoto
* 問合せ先  http://nob-log.info
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU Lesser General Public
* License as published by the Free Software Foundation; either
* version 2.1 of the License, or (at your option) any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
* Lesser General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public
* License along with this library; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

class CAPTCHA extends SC_Plugin_Base {

    /**
     * コンストラクタ
     *
     */
    public function __construct(array $arrSelfInfo) {
        parent::__construct($arrSelfInfo);
    }

    /**
     * インストール
     * installはプラグインのインストール時に実行されます.
     * 引数にはdtb_pluginのプラグイン情報が渡されます.
     *
     * @param array $arrPlugin plugin_infoを元にDBに登録されたプラグイン情報(dtb_plugin)
     * @return void
     */
    function install($arrPlugin) {
        // ファイルコピー
        if(copy(PLUGIN_UPLOAD_REALDIR . "CAPTCHA/logo.png", PLUGIN_HTML_REALDIR . "CAPTCHA/logo.png") === false);
    }

    /**
     * アンインストール
     * uninstallはアンインストール時に実行されます.
     * 引数にはdtb_pluginのプラグイン情報が渡されます.
     *
     * @param array $arrPlugin プラグイン情報の連想配列(dtb_plugin)
     * @return void
     */
    function uninstall($arrPlugin) {
        // ファイル削除
        if(SC_Helper_FileManager_Ex::deleteFile(PLUGIN_HTML_REALDIR . "CAPTCHA/logo.png") === false); print_r("失敗");
    }

    /**
     * 稼働
     * enableはプラグインを有効にした際に実行されます.
     * 引数にはdtb_pluginのプラグイン情報が渡されます.
     *
     * @param array $arrPlugin プラグイン情報の連想配列(dtb_plugin)
     * @return void
     */
    function enable($arrPlugin) {
        // nop
    }

    /**
     * 停止
     * disableはプラグインを無効にした際に実行されます.
     * 引数にはdtb_pluginのプラグイン情報が渡されます.
     *
     * @param array $arrPlugin プラグイン情報の連想配列(dtb_plugin)
     * @return void
     */
    function disable($arrPlugin) {
        // nop
    }


    /**
     * LC_Page_Contact
     */
    function contact_action_after($objPage) {
        $this->img_name  = md5(session_id()) . '.png';

        switch ($objPage->getMode()) {
            case 'confirm':
                // CAPTCHAチェック
                $arrErr             = $this->checkError();
                $objPage->arrErr    = array_merge($objPage->arrErr, $arrErr);

                if (SC_Utils_Ex::isBlank($objPage->arrErr)) {
                    // エラー無しで完了画面
                    $objPage->tpl_mainpage = 'contact/confirm.tpl';
                    $objPage->tpl_title = 'お問い合わせ(確認ページ)';
                } else {
                    $objPage->tpl_mainpage = 'contact/index.tpl';
                    $objPage->tpl_title = 'お問い合わせ(入力ページ)';
                }
                break;
        }
        $this->createCaptcha($objPage);
    }


    /**
     * 入力内容のチェックを行なう.
     *
     * @param SC_FormParam $objFormParam SC_FormParam インスタンス
     * @return array 入力チェック結果の配列
     */
    function checkError() {
        // 入力データを渡す。
        if ($_POST['input_captcha_auth'] != $_SESSION['captcha_auth']) {
            $arrErr['input_captcha_auth'] = '入力されたコードが正しくありません。';
        } else {
            $arrErr['input_captcha_auth'] = '';
            unset($_SESSION['captcha_auth']);
        }
        // 画像を削除する
        unlink(IMAGE_TEMP_REALDIR . $this->img_name);
        return $arrErr;
    }

    /*
     * CAPTCHAを生成する
     */
    function createCaptcha($objPage) {

        $save_path = IMAGE_TEMP_REALDIR . $this->img_name;

        // PEARのパスを追加
        set_include_path(get_include_path() . PATH_SEPARATOR . PLUGIN_UPLOAD_REALDIR . "CAPTCHA");
        require_once "Text/CAPTCHA.php";

        $optins = array(
            'width'        => 200,
            'height'       => 60,
            'output'       => 'png',
            'imageOptions' => array(
                'font_size'        => 20 ,
                'font_path'        => DATA_REALDIR . 'fonts',
                'font_file'        => 'wlmaru20044.ttf',
                'text_color'       => "#69af00",
                'lines_color'      => "#7acc00",
                'background_color' => "#ededed")
            );

        $captcha = Text_CAPTCHA::factory('Image');
        $captcha->init($optins);

        if (PEAR::isError($captcha)) {
            printf('CAPTCHA 作成時にエラー: %s!',
            $captcha->getMessage());
            exit;
        }

        //画像を保存
        file_put_contents($save_path, $captcha->getCAPTCHAAsPNG());

        //画像文字列をセッションに格納
        $_SESSION['captcha_auth'] = $captcha->getPhrase();
        $objPage->captcha_img = IMAGE_TEMP_URLPATH . $this->img_name;
    }


    function addParam($class_name, $param) {
        if (strpos($class_name, 'LC_Page_Contact') !== false) {
            $param->addParam('画像認証', 'input_captcha_auth');
        }
    }

    function prefilterTransform(&$source, LC_Page_Ex $objPage, $filename) {

        $objTransform = new SC_Helper_Transform($source);
        $template_dir = PLUGIN_UPLOAD_REALDIR ."CAPTCHA/templates/";
        switch($objPage->arrPageLayout['device_type_id']) {
            // 端末種別：PC
            case DEVICE_TYPE_PC:
            // 端末種別：スマートフォン
            case DEVICE_TYPE_SMARTPHONE:
                if(strpos($filename, "contact/index.tpl") !== false) {
                    $objTransform->select("div.btn_area", 0)
                        ->insertBefore(file_get_contents($template_dir . "captcha.tpl"));
                }
                break;
        }
        $source = $objTransform->getHTML();
    }
}
