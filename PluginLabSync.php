<?php
class PluginLabSync{
  private $files = array();
  private $remote_host = false;
  private $settings = null;
  function __construct($data = array()) {
    if($data == true){$data = array();} // Buto issue.
    /**¨
     * ¨Include.
     */
    wfPlugin::includeonce('wf/form_v2');
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
    /**
     * Enable.
     */
    wfPlugin::enable('wf/bootstrap');
    wfPlugin::enable('form/form_v1');
    wfPlugin::enable('wf/table');
    wfPlugin::enable('wf/ajax');
    wfPlugin::enable('wf/bootstrapjs');
    wfPlugin::enable('wf/dom');
    wfPlugin::enable('wf/callbackjson');
    wfPlugin::enable('datatable/datatable_1_10_16');
    wfPlugin::enable('element/iframe_v1');
    wfPlugin::enable('wf/embed');
    wfPlugin::enable('twitter/bootstrap335v');
    /**
     * Only webmaster if not reading files.
     */
    $event = false;
    if(wfGlobals::get('event/plugin')=='lab/sync'){
      $event = true;
    }
    if(!$event && wfGlobals::get('method')!='files' && wfGlobals::get('method')!='upload_capture' && wfGlobals::get('method')!='delete_remote_do'  && wfGlobals::get('method')!='download_capture' && !wfUser::hasRole('webmaster')){
      exit('Role issue says PluginLabSync.');
    }
    /**
     * Layout path.
     */
    wfArray::set($GLOBALS, 'sys/layout_path', '/plugin/lab/sync/layout');
    /**
     * Memory.
     */
    ini_set('memory_limit', '2048M');
    /**
     * Time.
     */
    ini_set('max_execution_time', 120);
    /**
     * Settings.
     */
    $this->settings = new PluginWfArray(wfArray::get($GLOBALS, 'sys/settings/plugin_modules/'.wfArray::get($GLOBALS, 'sys/class').'/settings'));
  }
  /**
   * Check ip.
   */
  private function check_ip(){
    $settings = new PluginWfArray(wfPlugin::getModuleSettings());
    $check_ip = false;
    $ip = $settings->get('ip');
    /**
     * Set ip from webmaster signin.
     */
    if($settings->get('data_file')){
      $data_file = new PluginWfYml(wfGlobals::getAppDir().$settings->get('data_file'));
      if($data_file->get('webmaster/ip')){
        $ip[] = $data_file->get('webmaster/ip');
      }
    }
    /**
     * 
     */
    foreach ($ip as $key => $value) {
      if($value==wfServer::getRemoteAddr()){
        $check_ip = true;
        break;
      }
    }
    return $check_ip;
  }
  /**
   * Read files from remote host.
   */
  public function page_files(){
    $this->remote_host = true;
    $check_ip = $this->check_ip();
    if(!$check_ip){
      exit(serialize(array()));
    }else{
      $this->set_files();
      exit(serialize($this->files));
    }
  }
  private function set_files($path = null){
    $dir = wfGlobals::getAppDir().$path;
    $scan = scandir($dir);
    $web_folder_name = $this->getWebFolderName();
    foreach ($scan as $key => $value) {
      if(substr($value, 0, 1)=='.' && $value!='.htaccess'){
        continue;
      }
      if(is_dir($dir.'/'.$value)){
        $this->set_files($path.'/'.$value);
      }else{
        $xpath = $path;
        if(substr($path, 0, strlen($web_folder_name)+1) == '/'.$web_folder_name){
          $xpath = '/[web_folder]'.substr($path, strlen($web_folder_name)+1);
        }
        $data = null;
        if($this->remote_host){
          $data = array('remote_size' => filesize($dir.'/'.$value), 'remote_time' => filemtime($dir.'/'.$value));
        }else{
          $data = array('local_size' => filesize($dir.'/'.$value), 'local_time' => filemtime($dir.'/'.$value));
        }
        $this->files[$xpath.'/'.$value] = $data;
      }
    }
  }
  private function getWebFolderName(){
    return basename(wfGlobals::getWebDir());
  }
  private function getSettings(){
    $key = wfUser::getSession()->get('plugin/lab/sync/theme');
    $theme_active = new PluginWfArray();
    $theme_active->set('has_theme', false);
    if(strlen($key)){
      $user = wfUser::getSession();
      $settings = new PluginWfArray(wfPlugin::getModuleSettings());
      $settings->set('theme_active', $key);
      $settings->set('theme', wfSettings::getSettingsFromYmlString($settings->get('theme')));
      $theme_active = new PluginWfArray($settings->get("theme/".$settings->get('theme_active')));
      $theme_active->set('has_theme', false);
      if(strlen($user->get('plugin/lab/sync/theme'))){
        $theme_active->set('has_theme', true);
      }
      /**
       * If theme is set we set item.
       */
      if($theme_active->get('theme')=='*'){
        /**
         * All files.
         */
        $item = new PluginWfYml(__DIR__.'/data/item.yml'); 
        $item = $item->get();        
      }else{
        /**
         * Theme files.
         */
        $item = array();
        $item[] = array('value' => '/sys/*');
        $item[] = array('value' => '/theme/'.$theme_active->get('theme').'/*');
        $item[] = array('value' => '/[web_folder]/theme/'.$theme_active->get('theme').'/*');
        $item[] = array('value' => '/[web_folder]/index.php');
        $item[] = array('value' => '/[web_folder]/.htaccess');
        $item[] = array('value' => '/[web_folder]/web.config');
        wfPlugin::includeonce('theme/analysis');
        $ta = new PluginThemeAnalysis(true);
        $ta->setData($theme_active->get('theme'));
        foreach ($ta->data->get() as $key => $value) {
          $i = new PluginWfArray($value);
          $item[] = array('value' => '/plugin/'.$i->get('name').'/*');
          $item[] = array('value' => '/[web_folder]/plugin/'.$i->get('name').'/*');
        }
        /**
         * Check for settings in theme config/settings.yml.
         * plugin:
            lab:
              sync:
                data:
                  external_folders:
                    - '/[web_folder]/more_content/*'
         */
        $external_folders = wfSettings::getSettingsAsObject('/theme/'.$theme_active->get('theme').'/config/settings.yml', 'plugin/lab/sync/data/external_folders');
        if($external_folders->get()){
          foreach ($external_folders->get() as $key => $value) {
            $item[] = array('value' => $value);
          }
        }
      }
      /**
       * 
       */
      $theme_active->set('item', $item);
    }
    return $theme_active;
  }
  private function getElementTheme(){
    /**
     * Create element to select theme.
     */
    $module_settings = new PluginWfArray(wfPlugin::getModuleSettings());
    $module_settings->set('theme', wfSettings::getSettingsFromYmlString($module_settings->get('theme')));
    $element = array();
    foreach ($module_settings->get('theme') as $key => $value) {
      $i = new PluginWfArray($value);
      $element[] = wfDocument::createHtmlElement('a', $i->get('name'), array('class' => 'btn btn-primary', 'onclick' => "PluginLabSync.theme_select(this)", 'data-key' => $key));
    }
    return $element;
  }
  public function page_theme_select(){
    /**
     * Select a theme via ajax.
     */
    wfUser::setSession('plugin/lab/sync/theme', wfRequest::get('key'));
    exit(json_encode(array('success' => true)));
  }
  public function page_start(){
    wfPlugin::includeonce('wf/yml');
    $settings = $this->getSettings();
    $page = new PluginWfYml(__DIR__.'/page/start.yml');
    $page->setByTag(array('settings' => wfHelp::getYmlDump($settings->get())));
    $page->setByTag($settings->get());
    $page->setByTag(array('element_theme' => $this->getElementTheme()));
    /**
     * Insert admin layout from theme.
     */
    $page = wfDocument::insertAdminLayout($this->settings, 1, $page);
    /**
     * 
     */
    $json = json_encode(array('class' => wfGlobals::get('class')));
    $page->setByTag(array('json' => 'var app = '.$json));
    /**
     * Insert admin layout from theme.
     */
    wfDocument::mergeLayout($page->get());
  }
  public function page_zip(){
    /**
     * Settings.
     */
    $settings = $this->getSettings();
    /**
     * Name of zip-file when download.
     */
    $download_name = 'ButoTheme_'.$settings->get('theme').'_'.date('ymdHis').'.zip';
    $download_name = str_replace('/', '_', $download_name);
    /**
     * Where zip file should be put...
     */
    $zip_filename = wfGlobals::getAppDir().'/'.$download_name;
    /**
     * Local files.
     */
    $this->set_files();
    $local_files = $this->files;
    /**
     * Filter.
     */
    foreach ($local_files as $key => $value) {
      $local_files[$key]['allow'] = false;
      if($settings->get('item')){
        foreach ($settings->get('item') as $key2 => $value2) {
          if($this->match_wildcard($value2['value'], $key)>0){
            $local_files[$key]['allow'] = true;
            continue;
          }
        }
      }
    }
    /**
     * Remove files.
     */
    foreach ($local_files as $key => $value) {
      if(!$local_files[$key]['allow']){
        unset($local_files[$key]);
      }
    }
    /**
     * Init ZipArchive.
     */
    $zip_archive = new ZipArchive();
    $zip_archive->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    /**
     * Add /config/settings.yml.
     * Copy existing file, edit theme param, save.
     * This file are to be deleted after zip closed.
     */
    $zip_settings_file = __DIR__.'/data/zip_settings_copy.yml';
    wfFilesystem::copyFile(__DIR__.'/data/zip_settings.yml', $zip_settings_file);
    $zip_settings_copy = new PluginWfYml($zip_settings_file);
    $zip_settings_copy->set('theme', $settings->get('theme'));
    $zip_settings_copy->save();
    $zip_archive->addFile($zip_settings_file, 'config/settings.yml');
    /**
     * Add files to zip archive.
     */
    foreach ($local_files as $key => $value) {
      $zip_archive->addFile(wfGlobals::getAppDir().$this->replaceWebDir($key), substr($this->replaceWebDir($key), 1));
    }
    $zip_archive->close();
    wfFilesystem::delete($zip_settings_file);
    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=$download_name"); 
    header("Pragma: no-cache"); 
    header("Expires: 0"); 
    readfile("$zip_filename");
    exit;
  }
  private function match_wildcard( $wildcard_pattern, $haystack ) {
     $regex = str_replace(
       array("\*", "\?"), // wildcard chars
       array('.*','.'),   // regexp chars
       preg_quote($wildcard_pattern)
     );
     return preg_match('#^'.$regex.'$#is', $haystack);
  }
  /**
   * When client ask server for list of files.
   */
  public function page_read(){
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
    $settings = $this->getSettings();
    $element = new PluginWfYml(__DIR__.'/element/read.yml');
    /**
     * Local files.
     */
    $this->set_files();
    $local_files = $this->files;
    /**
     * Remote files.
     */
    $url = $this->getUrl('files');
    $content = @file_get_contents($url);
    if($content === false){
      exit("Error when call url $url!");
    }
    $remote_files = @unserialize($content);
    if($remote_files === false){
      wfHelp::yml_dump($content, true);
      exit("Content from url $url could not be handled!");
    }
    $element->setByTag(array('remote_files_count' => sizeof($remote_files)));
    /**
     * Merge existing remote to local.
     */
    foreach ($local_files as $key => $value) {
      if(isset($remote_files[$key])){
        $local_files[$key]['exist'] = 'both';
        $local_files[$key]['remote_size'] = $remote_files[$key]['remote_size'];
        $local_files[$key]['remote_time'] = $remote_files[$key]['remote_time'];
      }else{
        $local_files[$key]['exist'] = 'local';
      }
    }
    /**
     * Merge not existing to local.
     */
    foreach ($remote_files as $key => $value) {
      if(isset($local_files[$key])){
        //
      }else{
        $local_files[$key]['exist'] = 'remote';
        $local_files[$key]['remote_size'] = $remote_files[$key]['remote_size'];
        $local_files[$key]['remote_time'] = $remote_files[$key]['remote_time'];
      }
    }
    /**
     * Set size diff.
     */
    foreach ($local_files as $key => $value){
      $item = new PluginWfArray($value);
      if($item->get('local_size') && $item->get('remote_size') && $item->get('local_size') != $item->get('remote_size')){
        $local_files[$key]['size_diff'] = 'sizediff';
      }else{
        $local_files[$key]['size_diff'] = '';
      }
    }
    /**
     * Filter.
     */
    foreach ($local_files as $key => $value) {
      $local_files[$key]['allow'] = false;
      if($settings->get('item')){
        foreach ($settings->get('item') as $key2 => $value2) {
          if($this->match_wildcard($value2['value'], $key)>0){
            $local_files[$key]['allow'] = true;
            continue;
          }
        }
      }
    }
    /**
     * Time diff.
     */
    foreach ($local_files as $key => $value){
      $item = new PluginWfArray($value);
      if($item->get('local_time') && $item->get('remote_time') && $item->get('local_time') != $item->get('remote_time')){
        $local_files[$key]['time_diff'] = 'timediff';
      }else{
        $local_files[$key]['time_diff'] = '';
      }
    }
    /**
     * Local newer.
     */
    foreach ($local_files as $key => $value){
      $item = new PluginWfArray($value);
      if($item->get('local_time') && $item->get('remote_time')){
        if($item->get('local_time') > $item->get('remote_time') && $item->get('local_time') > strtotime($settings->get('local_time'))){
          $local_files[$key]['local_newer'] = 'local_newer';
        }elseif($item->get('local_time') < $item->get('remote_time')){
        #}elseif($item->get('local_time') < $item->get('remote_time') && $item->get('remote_time') > strtotime($settings->get('local_time'))){
          $local_files[$key]['local_newer'] = 'remote_newer';
        }else{
          $local_files[$key]['local_newer'] = '';
        }
      }else{
        $local_files[$key]['local_newer'] = '';
      }
    }
    /**
     * 
     */
    $tbody = array();
    $i=0;
    foreach ($local_files as $key => $value) {
      $item = new PluginWfArray($value);
      if(!$item->get('allow')){
        continue;
      }
      $i++;
      if($item->get('exist')=='local'){
      }
      if($item->get('exist')=='remote'){
        $onclick = "PluginLabSync.download(this)";
        $glyphicon = 'download';
        $title = 'Download file from server.';
      }else{
        $onclick = "PluginLabSync.upload(this)";
        $glyphicon = 'upload';
        $title = 'Upload file to server.';
      }
      $tbody[] = wfDocument::createHtmlElement('tr', array(
       wfDocument::createHtmlElement('td', $i),
       wfDocument::createHtmlElement('td', array(
         wfDocument::createHtmlElement('text', $key), 
         wfDocument::createHtmlElement('a', array(wfDocument::createHtmlElement('span', null, array('class' => 'glyphicon glyphicon-'.$glyphicon))), array('onclick' => $onclick, 'title' => $title, 'class' => 'btn_upload', 'data-file' => urlencode($key), 'data-exist' => $item->get('exist'))),
         wfDocument::createHtmlElement('a', array(wfDocument::createHtmlElement('span', null, array('class' => 'glyphicon glyphicon-trash'))), array('onclick' => "PluginLabSync.delete_form(this)", 'title' => 'Delete file.', 'class' => '', 'data-file' => urlencode($key), 'data-exist' => $item->get('exist')))
         )),
       wfDocument::createHtmlElement('td', '('.$item->get('exist').')', array('class' => 'td_exist')),
       wfDocument::createHtmlElement('td', ($item->get('size_diff')?'('.$item->get('size_diff').')':null) ),
       wfDocument::createHtmlElement('td', $item->get('local_size')),
       wfDocument::createHtmlElement('td', $item->get('remote_size')),
       wfDocument::createHtmlElement('td', ($item->get('local_time')?date('ymd H:i:s', $item->get('local_time')):null) ),
       wfDocument::createHtmlElement('td', ($item->get('remote_time')?date('ymd H:i:s', $item->get('remote_time')):null) ),
       wfDocument::createHtmlElement('td',   ($item->get('time_diff')?'('.$item->get('time_diff').')':null) ),
       wfDocument::createHtmlElement('td', ($item->get('local_newer')?'('.$item->get('local_newer').')':null), array('class' => 'td_local_newer'))
      ));
    }
    $element->setByTag(array('tbody' => $tbody));
    wfDocument::renderElement($element->get());
    /**
     * Count.
     */
    $local_count = 0;
    $remote_count = 0;
    $local_newer_count = 0;
    foreach ($local_files as $key => $value) {
      if($value['allow'] && $value['exist']=='local'){
        $local_count++;
      }
      if($value['allow'] && $value['exist']=='remote'){
        $remote_count++;
      }
      if($value['allow'] && $value['local_newer']=='local_newer'){
        $local_newer_count++;
      }
    }
    $script = array();
    $script[] = wfDocument::createHtmlElement('script', "document.getElementById('badge_local').innerHTML='$local_count';");
    $script[] = wfDocument::createHtmlElement('script', "document.getElementById('badge_remote').innerHTML='Remote: $remote_count';");
    $script[] = wfDocument::createHtmlElement('script', "document.getElementById('badge_local_newer').innerHTML='$local_newer_count';");
    $script[] = wfDocument::createHtmlElement('script', "PluginLabSync.sound();");
    wfDocument::renderElement($script);
  }
  /**
   * Get data for uploading purpose.
   */
  private function getUploadData(){
    $data = new PluginWfArray();
    $filename = wfRequest::get('key');
    $data->set('filename', $filename);
    $filename = $this->replaceWebDir($filename);
    $data->set('content', file_get_contents(wfGlobals::getAppDir().$filename));
    return $data;
  }
  /**
   * Get url.
   */
  private function getUrl($method){
    $settings = $this->getSettings();
    return $settings->get('url').'/'.$method;
  }
  /**
   * Save files from remote server.
   * This method support multiple files.
   */
  public function page_upload_capture(){
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'files' => null, 'message' => null));
    /**
     * Check IP.
     */
    $check_ip = $this->check_ip();
    if(!$check_ip){
      $result->set('success', false);
      $result->set('message', 'Your ip is not registred!');
      exit(serialize($result->get()));
    }
    /**
     * Files.
     */
    $files = wfRequest::getAll();
    try {
      /**
       * Save files and set size.
       */
      foreach ($files as $key => $value) {
        $filename = $value['filename'];
        $filename = $this->replaceWebDir($filename);
        /**
         * Create dir if not exist.
         */
        $dirname = dirname(wfGlobals::getAppDir().$filename);
        if(!wfFilesystem::fileExist($dirname)){
          mkdir($dirname, 0777, true);
        }
        /**
         * Save file.
         */
        $size = file_put_contents(wfGlobals::getAppDir().$filename, $value['content']);
        unset($files[$key]['content']);
        $files[$key]['size'] = $size;
      }
    }catch (Exception $e) {
      $result->set('success', false);
      $result->set('message', $e->getMessage());
      exit(serialize($result->get()));
    }
    
    /**
     * Send back result.
     */
    $result->set('files', $files);
    exit(serialize($result->get()));
  }
  /**
   * Upload file to remote server.
   * Method page_upload_capture() handle response.
   */
  public function page_upload(){
    $data = $this->getUploadData();
    $url = $this->getUrl('upload_capture');
    $params = new PluginWfArray();
    $params->set(true, array('filename' => $data->get('filename'), 'content' => $data->get('content')));
    wfPlugin::includeonce('server/push');
    $push = new PluginServerPush();
    $result = $push->push($url, $params->get());
    $json = null;
    try {
      $json = json_encode(unserialize($result));
    } catch (Exception $exc) {
      exit($result);
    }
    exit($json);
  }
  private function replaceWebDir($filename){
    return str_replace('[web_folder]', $this->getWebFolderName(), $filename);
  }
  /**
   * Download file client side.
   */
  public function page_download(){
    /**
     * Ask server for file.
     */
    $filename = wfRequest::get('key');
    $url = $this->getUrl('download_capture');
    wfPlugin::includeonce('server/push');
    $push = new PluginServerPush();
    $result = $push->push($url, array('filename' => $filename));
    $result = new PluginWfArray(unserialize($result));
    if($result->get('success')){
      $filename = $this->replaceWebDir($filename);
      /**
       * Create dir if not exist.
       */
      $dirname = dirname(wfGlobals::getAppDir().$filename);
      if(!wfFilesystem::fileExist($dirname)){
        mkdir($dirname, 0777, true);
      }
      /**
       * Save file.
       */
      $size = file_put_contents(wfGlobals::getAppDir().$filename, $result->get('content'));
      $result->set('content', null);
      $result->set('size', $size);
    }
    exit(json_encode($result->get()));
  }
  /**
   * Download file server side.
   */
  public function page_download_capture(){
    $filename = wfRequest::get('filename');
    $filename = $this->replaceWebDir($filename);
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'filename' => $filename, 'message' => null, 'content' => null));
    /**
     * Check IP.
     */
    $check_ip = $this->check_ip();
    if(!$check_ip){
      $result->set('success', false);
      $result->set('message', 'Your ip is not registred!');
      exit(serialize($result->get()));
    }
    /**
     * Get content and put in serialize array.
     */
    $content = file_get_contents(wfGlobals::getAppDir().$filename);
    $result->set('content', $content);
    exit(serialize($result->get()));
  }
  public function page_delete_local(){
    $filename = wfRequest::get('key');
    $filename = $this->replaceWebDir($filename);
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'filename' => $filename, 'message' => null));
    if($this->check_ip()){
      if(wfFilesystem::fileExist(wfGlobals::getAppDir().$filename)){
        wfFilesystem::delete(wfGlobals::getAppDir().$filename);
      }else{
        $result->set('success', false);
        $result->set('message', 'File does not exist local.');
      }
    }else{
      $result->set('success', false);
      $result->set('message', 'IP issue.');
    }
    exit(json_encode($result->get()));
  }
  /**
   * S2C request.
   * Call this from local server.
   */
  public function page_delete_remote(){
    $filename = wfRequest::get('key');
    $url = $this->getUrl('delete_remote_do');
    $params = new PluginWfArray();
    $params->set('filename', $filename);
    wfPlugin::includeonce('server/push');
    $push = new PluginServerPush();
    $result = $push->push($url, $params->get());
    $unserialize = @unserialize($result);
    if($unserialize===false){
      exit($result);
    }else{
      exit(json_encode($unserialize));
    }
  }
  /**
   * S2S request.
   */
  public function page_delete_remote_do(){
    $filename = wfRequest::get('filename');
    $filename = $this->replaceWebDir($filename);
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'filename' => $filename, 'message' => null));
    /**
     * Check IP.
     */
    $check_ip = $this->check_ip();
    if(!$check_ip){
      $result->set('success', false);
      $result->set('message', 'Your ip is not registred!');
      exit(serialize($result->get()));
    }
    /**
     * Get content and put in serialize array.
     */
    if(wfFilesystem::fileExist(wfGlobals::getAppDir().$filename)){
      wfFilesystem::delete(wfGlobals::getAppDir().$filename);
    }else{
      $result->set('success', false);
      $result->set('message', 'File does not exist remote.');
    }
    exit(serialize($result->get()));
  }
  public function event_signin(){
    /**
     * If role webmaster we store ip to check in read procedure.
     */
    if(wfUser::hasRole('webmaster')){
      $settings = wfPlugin::getPluginModulesOne('lab/sync');
      if($settings && $settings->get('settings/data_file')){
        $data_file = new PluginWfYml(wfGlobals::getAppDir().$settings->get('settings/data_file'));
        $data_file->set('webmaster/ip', wfServer::getRemoteAddr());
        $data_file->set('webmaster/date', date('Y-m-d H:i:s'));
        $data_file->save();
      }
    }
  }
}
