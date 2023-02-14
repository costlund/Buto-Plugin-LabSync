<?php
class PluginLabSync{
  private $files = array();
  private $remote_host = false;
  private $settings = null;
  private $files_excluded = array();
  private $ftp = null;
  private $dir = null;
  function __construct($data = array()) {
    if($data == true){$data = array();} // Buto issue.
    /**
     * Excluded.
     */
    $this->files_excluded = array('/config/settings.yml');
    /**
     * Include.
     */
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
    /**
     * Enable.
     */
    wfPlugin::enable('theme/include');
    wfPlugin::enable('icons/octicons');
    wfPlugin::enable('wf/table');
    wfPlugin::enable('bootstrap/navtabs_v1');
    /**
     * Only webmaster if not reading files.
     */
    $event = false;
    if(wfGlobals::get('event/plugin')=='lab/sync'){
      $event = true;
    }
    if(!$event && wfGlobals::get('method')!='files' && wfGlobals::get('method')!='upload_capture' && wfGlobals::get('method')!='delete_remote_do' && wfGlobals::get('method')!='delete_remote_folder_do'  && wfGlobals::get('method')!='download_capture' && !wfUser::hasRole('webmaster')){
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
    ini_set('max_execution_time', 60*20);
    /**
     * Settings.
     */
    $this->settings = new PluginWfArray(wfArray::get($GLOBALS, 'sys/settings/plugin_modules/'.wfArray::get($GLOBALS, 'sys/class').'/settings'));
    $buto_data = new PluginWfYml(wfGlobals::getThemeButoDataDir().'/plugin_lab_sync.yml');
    $this->settings->set('remote', $buto_data->get('remote'));
    $this->settings->set('theme', $buto_data->get('theme'));
    $this->settings->set('token', $buto_data->get('token'));
    /**
     * version
     */
    if($this->settings->get('theme')){
      foreach($this->settings->get('theme') as $k => $v){
        $manifest = new PluginWfYml(wfGlobals::getAppDir()."/theme/".$v['theme'].'/config/manifest.yml');
        $this->settings->set("theme/$k/version", $manifest->get('version'));
        if($this->settings->get("theme/$k/version") != $this->settings->get("theme/$k/log/version")){
          $this->settings->set("theme/$k/version_diff", 'Yes');
        }else{
          $this->settings->set("theme/$k/version_diff", '');
        }
        if($this->settings->get("theme/$k/log/upload")){
          $date1 = new DateTime(date('Y-m-d'));
          $date2 = new DateTime(substr($this->settings->get("theme/$k/log/upload"), 0, 10));
          $interval = $date1->diff($date2);
          $this->settings->set("theme/$k/upload_days", $interval->days);
        }else{
          $this->settings->set("theme/$k/upload_days", '');
        }
      }
    }
    /**
     * remote_data
     */
    if(wfRequest::get('remote') && $this->settings->get('remote/'.wfRequest::get('remote'))){
      $this->settings->set('remote_data', $this->settings->get('remote/'.wfRequest::get('remote')));
    }else{
      $this->settings->set('remote_data', null);
    }
    if(!$this->settings->get('remote_data/dir')){
      $this->dir = wfGlobals::getAppDir();
    }else{
      $this->dir = wfGlobals::get('host_dir').$this->settings->get('remote_data/dir');
    }
  }
  private function set_ftp(){
    $settings = $this->getSettings();
    wfPlugin::includeonce('php/ftp_v1');
    $this->ftp = new PluginPhpFtp_v1();
    if(!$settings->get('ftp/dir')){
      $settings->set('ftp/dir', '/');
    }
    $this->ftp->setData($settings->get('ftp'));
    $this->ftp->dir = $settings->get('ftp/dir');
    return null;
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
    if(!$ip){
      $ip = array();
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
  private function token_match($token){
    $match = false;
    if($this->settings->get('token')){
      foreach($this->settings->get('token') as $v){
        $i = new PluginWfArray($v);
        if($i->get('value')==$token){
          $match = true;
          break;
        }
      }
    }
    return $match;
  }
  /**
   * Read files from remote host.
   */
  public function page_files(){
    $output = new PluginWfArray();
    $output->set('check_ip', false);
    $output->set('success', true);
    $output->set('files', null);
    $this->remote_host = true;
    $check_ip = $this->check_ip();
    $output->set('check_ip', $check_ip);
    $token_match = $this->token_match(wfRequest::get('token'));
    if(false){
      $output->set('token', $this->settings->get());
    }
    $output->set('token_match', $token_match);
    if(!$check_ip && !wfUser::hasRole('webmaster') && !$token_match){
      $output->set('success', false);
      exit(serialize($output->get()));
    }else{
      $this->set_files();
      $output->set('files', $this->files);
      if(wfRequest::get('output')=='yml'){
        wfHelp::yml_dump($output->get());
      }else if(wfRequest::get('output')=='json'){
        exit(json_encode($output->get()));
      }else{
        exit(serialize($output->get()));
      }
    }
  }
  private function set_files($path = null){
    $dir = $this->dir.$path;
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
    return null;
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
      $settings = $this->settings;
      $settings->set('theme_active', $key);
      $theme_active = new PluginWfArray($settings->get("theme/".$settings->get('theme_active')));
      $theme_active->set('has_theme', false);
      /**
       * 
       */
      wfPlugin::includeonce('theme/analysis');
      $ta = new PluginThemeAnalysis(true);
      $ta->setData($theme_active->get('theme'));
      /**
       * 
       */
      if(strlen($user->get('plugin/lab/sync/theme'))){
        $theme_active->set('has_theme', true);
      }
      /**
       * Theme manifest.
       */
      if($theme_active->get('theme')!='*'){
        $manifest = new PluginWfYml('/theme/'.$theme_active->get('theme').'/config/manifest.yml');
        $theme_active->set('manifest', $manifest->get());
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
      $theme_active->set('plugin', $ta->data->get());
    }
    return $theme_active;
  }
  private function getElementTheme(){
    /**
     * Create element to select theme.
     */
    $module_settings = $this->settings;
    $element = array();
    /**
     * Table
     */
    $table = new PluginWfYml(__DIR__.'/element/table.yml');
    foreach($module_settings->get('theme') as $k => $v){
      $module_settings->set("theme/$k/row_click", "PluginLabSync.theme_select(this)");
      $module_settings->set("theme/$k/row_attribute", array('data-key' => $k));
    }
    $table->setByTag(array('data' => $module_settings->get('theme')));
    $element[] = $table->get();
    return $element;
  }
  public function page_theme_select(){
    /**
     * Select a theme via ajax.
     */
    wfUser::setSession('plugin/lab/sync/theme', wfRequest::get('key'));
    exit(json_encode(array('success' => true)));
  }
  private function log($page, $version = null){
    if(strlen(wfUser::getSession()->get('plugin/lab/sync/theme'))){
      $buto_data = new PluginWfYml(wfGlobals::getThemeButoDataDir().'/plugin_lab_sync.yml');
      $buto_data->set('theme/'.wfUser::getSession()->get('plugin/lab/sync/theme').'/log/'.$page, date('Y-m-d H:i:s'));
      if($version){
        $buto_data->set('theme/'.wfUser::getSession()->get('plugin/lab/sync/theme').'/log/version', $version);
      }
      $buto_data->save();
    }
    return null;
  }
  public function page_start(){
    $this->log('start');
    wfPlugin::includeonce('wf/yml');
    $settings = $this->getSettings();
    if($settings->get('token')){
      $settings->set('token', '****');
    }
    if($settings->get('ftp/password')){
      $settings->set('ftp/password', '****');
    }
    $page = new PluginWfYml(__DIR__.'/page/start.yml');
    $page->setByTag(array('settings' => $settings->get()));
    $page->setByTag($settings->get());
    $page->setByTag(array('element_theme' => $this->getElementTheme()), 'rs', true);
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
  private function export_get_files($settings){
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
     * Exclude
     */
    if($settings->get('exclude')){
      foreach ($local_files as $key => $value) {
        foreach ($settings->get('exclude') as $value2) {
          if($this->match_wildcard($value2, $key)){
            unset($local_files[$key]);
          }
        }
      }
    }
    return $local_files;
  }
  public function page_export(){
    /**
     * log
     */
    $this->log('export');
    /**
     * Settings.
     */
    $settings = $this->getSettings();
    /**
     * param check
     */
    if(!$settings->get('export/folder')){
      exit('Param export/folder is not set!');
    }
    /**
     * Local files.
     */
    $local_files = $this->export_get_files($settings);
    /**
     * Copy to export folder.
     */
    foreach ($local_files as $key => $value) {
      $source = wfGlobals::getAppDir().$this->replaceWebDir($key);
      $dest = $settings->get('export/folder').$this->replaceWebDir($key);
      wfFilesystem::copyFile($source, $dest);
    }
    /**
     * Add /config/settings.yml.
     * Copy existing file, edit theme param, save.
     * This file are to be deleted after zip closed.
     */
    $zip_settings_file = __DIR__.'/data/zip_settings_copy.yml';
    wfFilesystem::copyFile(__DIR__.'/data/zip_settings.yml', $zip_settings_file);
    $zip_settings_copy = new PluginWfYml($zip_settings_file);
    $zip_settings_copy->set('theme', $settings->get('theme'));
    /**
     * export/config
     */
    if($settings->get('export/config')){
      $zip_settings_copy->merge($settings->get('export/config'));
    }
    /**
     * save
     */
    $zip_settings_copy->save();
    wfFilesystem::copyFile($zip_settings_file, $settings->get('export/folder').'/config/settings.yml');
    /**
     * delete
     */
    wfFilesystem::delete($zip_settings_file);
    /**
     * 
     */
    exit('Exported '.(sizeof($local_files)+1).' files to folder '.$settings->get('export/folder').'.');
  }
  public function page_zip(){
    $this->log('zip');
    /**
     * Settings.
     */
    $settings = $this->getSettings();
    /**
     * Version.
     */
    $version = '';
    if($settings->get('manifest/version')){
      $version = '_'.$settings->get('manifest/version');
    }
    /**
     * Name of zip-file when download.
     */
    $download_name = 'ButoTheme_'.$settings->get('theme').'_'.date('ymdHis').$version.'.zip';
    $download_name = str_replace('/', '_', $download_name);
    /**
     * Where zip file should be put...
     */
    $zip_filename = wfGlobals::getAppDir().'/'.$download_name;
    /**
     * Local files.
     */
    $local_files = $this->export_get_files($settings);
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
    /**
     * zip/config
     */
    if($settings->get('zip/config')){
      $zip_settings_copy->merge($settings->get('zip/config'));
    }
    /**
     * 
     */
    $zip_settings_copy->save();
    $zip_archive->addFile($zip_settings_file, 'config/settings.yml');
    /**
     * Add files to zip archive.
     */
    foreach ($local_files as $key => $value) {
      $zip_archive->addFile(wfGlobals::getAppDir().$this->replaceWebDir($key), substr($this->replaceWebDir($key), 1));
    }
    $zip_archive->close();
    /**
     * 
     */
    wfFilesystem::delete($zip_settings_file);
    /**
     * 
     */
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
    $this->log('read');
    $type_of_sync = wfRequest::get('type_of_sync');
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
    $remote_files = array();
    if($type_of_sync=='sync'){
      if($settings->get('ftp')){
        $this->set_ftp();
        $rawlist = $this->ftp->rawlist();
        $rawlist = $this->ftp->raw_list_top_level_7($rawlist);
        $remote_files = $this->ftp->rawlist_files($rawlist);
        /**
         * 
         */
        if(sizeof($remote_files)==0){
          exit("Ftp server does not return any data!");
        }
      }else{
        $url = $this->getUrl('files');
        if($settings->get('remote')){
          $url = $url.'?remote='.$settings->get('remote').'&token='.$settings->get('token');
        }
        $ctx = stream_context_create(array('http'=> array('timeout' => 60*5)));
        $content = @file_get_contents($url, false, $ctx);
        if($content === false){
          exit("Error when call url $url!");
        }
        $remote_files = @unserialize($content);
        if($remote_files === false){
          wfHelp::yml_dump($content);
          exit("Content from url $url could not be handled!");
        }
        /**
         * 
         */
        if(isset($remote_files['files']) && !$remote_files['files']){
          wfHelp::print($remote_files, true);
        }
        $remote_files = $remote_files['files'];
      }
    }
    /**
     * Remote files included in theme.
     */
    foreach ($remote_files as $key => $value) {
      if(in_array($key, $this->files_excluded)){
        $remote_files[$key]['theme_text'] = null;
        continue;
      }
      $remote_files[$key]['theme_text'] = '(theme_no)';
      if($settings->get('item')){
        foreach ($settings->get('item') as $key2 => $value2) {
          if($this->match_wildcard($value2['value'], $key)>0){
            $remote_files[$key]['theme_text'] = '(theme_yes)';
            continue;
          }
        }
      }
    }
    /**
     * Merge existing remote to local.
     */
    foreach ($local_files as $key => $value) {
      if(isset($remote_files[$key])){
        $local_files[$key]['exist'] = 'both';
        $local_files[$key]['remote_size'] = $remote_files[$key]['remote_size'];
        $local_files[$key]['remote_time'] = $remote_files[$key]['remote_time'];
        $local_files[$key]['theme_text'] = $remote_files[$key]['theme_text'];
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
        $local_files[$key]['theme_text'] = $remote_files[$key]['theme_text'];
      }
    }
    /**
     * Exclude
     */
    if($settings->get('exclude')){
      foreach ($local_files as $key => $value) {
        foreach ($settings->get('exclude') as $value2) {
          if($this->match_wildcard($value2, $key)){
            unset($local_files[$key]);
          }
        }
      }
    }
    /**
     * Files count.
     */
    $element->setByTag(array('total_files_count' => sizeof($local_files)));
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
          /**
           * Files on server but not included in theme.
           */
          if(isset($local_files[$key]['theme_text']) && $local_files[$key]['theme_text']=='(theme_no)'){
            $local_files[$key]['allow'] = true;
            continue;
          }
          /**
           * Files not included in theme.
           */
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
        $title = 'Download file from server.';
        $icon_upload = wfDocument::createWidget('icons/octicons', 'svg', array('name' => 'cloud-download'));
      }else{
        $onclick = "PluginLabSync.upload(this)";
        $title = 'Upload file to server.';
        $icon_upload = wfDocument::createWidget('icons/octicons', 'svg', array('name' => 'cloud-upload'));
      }
      $icon_trashcan = wfDocument::createWidget('icons/octicons', 'svg', array('name' => 'trashcan'));
      $tbody[] = wfDocument::createHtmlElement('tr', array(
       wfDocument::createHtmlElement('td', $i),
       wfDocument::createHtmlElement('td', array(
         wfDocument::createHtmlElement('text', $key), 
         wfDocument::createHtmlElement('a', array($icon_upload), array('onclick' => $onclick, 'title' => $title, 'class' => 'btn_upload', 'data-file' => urlencode($key), 'data-exist' => $item->get('exist'))),
         wfDocument::createHtmlElement('a', array($icon_trashcan), array('onclick' => "PluginLabSync.delete_form(this)", 'title' => 'Delete file.', 'class' => '', 'data-file' => urlencode($key), 'data-dir' => urlencode(dirname($key)), 'data-exist' => $item->get('exist'))),
         wfDocument::createHtmlElement('a', 'hidden_btn_delete_remote', array('style' => 'display:none', 'onclick' => "PluginLabSync.delete_remote(this)", 'title' => 'Delete remote file.', 'class' => 'btn_delete_all_theme_no', 'data-file' => urlencode($key), 'data-dir' => urlencode(dirname($key)), 'data-exist' => $item->get('exist')))
         )),
       wfDocument::createHtmlElement('td', '('.$item->get('exist').')', array('class' => 'td_exist')),
       wfDocument::createHtmlElement('td', ($item->get('size_diff')?'('.$item->get('size_diff').')':null), array('class' => 'td_sizediff') ),
       wfDocument::createHtmlElement('td', $item->get('local_size')),
       wfDocument::createHtmlElement('td', $item->get('remote_size')),
       wfDocument::createHtmlElement('td', ($item->get('local_time')?date('ymd H:i:s', $item->get('local_time')):null) ),
       wfDocument::createHtmlElement('td', ($item->get('remote_time')?date('ymd H:i:s', $item->get('remote_time')):null) ),
       wfDocument::createHtmlElement('td',   ($item->get('time_diff')?'('.$item->get('time_diff').')':null) ),
       wfDocument::createHtmlElement('td', ($item->get('local_newer')?'('.$item->get('local_newer').')':null), array('class' => 'td_local_newer')),
       wfDocument::createHtmlElement('td', $item->get('theme_text'), array('class' => 'td_theme'))
      ));
    }
    $element->setByTag(array('tbody' => $tbody));
    wfDocument::renderElement($element->get());
    /**
     * Count.
     */
    $local_count = 0;
    $sizediff_count = 0;
    $remote_count = 0;
    $local_newer_count = 0;
    $theme_no_count = 0;
    foreach ($local_files as $key => $value) {
      if($value['allow'] && $value['exist']=='local'){
        $local_count++;
      }
      if($value['allow'] && $value['size_diff']=='sizediff'){
        $sizediff_count++;
      }
      if($value['allow'] && $value['exist']=='remote'){
        $remote_count++;
      }
      if($value['allow'] && $value['local_newer']=='local_newer'){
        $local_newer_count++;
      }
      if(isset($value['theme_text']) && $value['allow'] && $value['theme_text']=='(theme_no)'){
        $theme_no_count++;
      }
    }
    $script = array();
    $script[] = wfDocument::createHtmlElement('script', "document.getElementById('badge_local').innerHTML='$local_count';");
    $script[] = wfDocument::createHtmlElement('script', "document.getElementById('badge_sizediff').innerHTML='$sizediff_count';");
    $script[] = wfDocument::createHtmlElement('script', "document.getElementById('badge_remote').innerHTML='Remote: $remote_count';");
    $script[] = wfDocument::createHtmlElement('script', "document.getElementById('badge_theme_no').innerHTML='$theme_no_count';");
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
    $ctx = stream_context_create(array('http'=> array('timeout' => 60*5)));
    $data->set('content', file_get_contents(wfGlobals::getAppDir().$filename, false, $ctx));
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
   * Save files from client server via PluginServerPush.
   * This method support multiple files.
   */
  public function page_upload_capture(){
    /**
     * Check IP.
     */
    $check_ip = $this->check_ip();
    $token_match = $this->token_match(wfRequest::get('token'));
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'files' => null, 'message' => null, 'remote' => wfRequest::get('remote'), 'check_ip' => $check_ip, 'token_match' => $token_match, 'remote_data_dir' => $this->settings->get('remote_data/dir'), 'dir' => $this->dir));
    if(!$check_ip && !$token_match){
      $result->set('success', false);
      $result->set('message', 'Your ip or token is not registred!');
      exit(serialize($result->get()));
    }
    /**
     * Files.
     */
    $files = wfRequest::get('files');
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
        $dirname = dirname($this->dir.$filename);
        if(!wfFilesystem::fileExist($dirname)){
          mkdir($dirname, 0777, true);
        }
        /**
         * Save file.
         */
        $size = file_put_contents($this->dir.$filename, $value['content']);
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
    $settings = $this->getSettings();
    $this->log('upload', $settings->get('manifest/version'));
    if(!$settings->get('ftp')){
      $data = $this->getUploadData();
      $url = $this->getUrl('upload_capture');
      $params = new PluginWfArray();
      $params->set('files', array(array('filename' => $data->get('filename'), 'content' => $data->get('content'))));
      $params->set('remote', $settings->get('remote'));
      $params->set('token', $settings->get('token'));
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
    }else{
      $this->set_ftp();
      $data = $this->getUploadData();
      $local_file = $this->replaceWebDir($data->get('filename'));
      $remote_file = $this->replaceWebDirFtp($data->get('filename'));
      $bool = $this->ftp->put($remote_file, wfGlobals::getAppDir().$local_file);
      $result = new PluginWfArray(array('success' => $bool, 'files' => array($local_file), 'message' => "File $local_file was uploaded as $remote_file with FTP."));
      $json = json_encode($result->get());
      exit($json);
    }
  }
  private function replaceWebDirFtp($filename){
    $settings = $this->getSettings();
    return str_replace('[web_folder]', $settings->get('ftp/web_folder'), $filename);
  }
  private function replaceWebDir($filename){
    return str_replace('[web_folder]', $this->getWebFolderName(), $filename);
  }
  /**
   * Download file client side.
   */
  public function page_download(){
    $this->log('download');
    /**
     * Ask server for file.
     */
    $settings = $this->getSettings();
    if(!$settings->get('ftp')){
      $filename = wfRequest::get('key');
      $url = $this->getUrl('download_capture');
      wfPlugin::includeonce('server/push');
      $push = new PluginServerPush();
      $result = $push->push($url, array('filename' => $filename, 'remote' => $settings->get('remote'), 'token' => $settings->get('token')));
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
    }else{
      $this->set_ftp();
      $filename = wfRequest::get('key');
      $local_file = $this->replaceWebDir($filename);
      $remote_file = $this->replaceWebDirFtp($filename);
      $bool = $this->ftp->get(wfGlobals::getAppDir().$local_file, $remote_file);
      $result = new PluginWfArray(array('success' => $bool, 'files' => array($remote_file), 'message' => "File $remote_file was downloaded with FTP."));
      $json = json_encode($result->get());
      exit($json);
    }
  }
  /**
   * Download file server side.
   */
  public function page_download_capture(){
    $filename = wfRequest::get('filename');
    $filename = $this->replaceWebDir($filename);
    /**
     * Check IP.
     */
    $check_ip = $this->check_ip();
    $token_match = $this->token_match(wfRequest::get('token'));
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'check_ip' => $check_ip, 'token_match' => $token_match, 'filename' => $filename, 'message' => null, 'content' => null, 'dir' => $this->dir));
    /**
     *
     */
    if(!$check_ip && !$token_match){
      $result->set('success', false);
      $result->set('message', 'Your ip or token is not registred!');
      exit(serialize($result->get()));
    }
    /**
     * Get content and put in serialize array.
     */
    $content = file_get_contents($this->dir.$filename);
    $result->set('content', $content);
    exit(serialize($result->get()));
  }
  /**
   * Delete file on client.
   */
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
    $this->log('delete_remote');
    $settings = $this->getSettings();
    if(!$settings->get('ftp')){
      $filename = wfRequest::get('key');
      $url = $this->getUrl('delete_remote_do');
      $url .= '?remote='.$settings->get('remote').'&token='.$settings->get('token');
      //wfHelp::yml_dump($url, true);
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
    }else{
      $this->set_ftp();
      $filename = wfRequest::get('key');
      $remote_file = $this->replaceWebDirFtp($filename);
      $bool = $this->ftp->delete($remote_file);
      $result = new PluginWfArray(array('success' => $bool, 'files' => array($remote_file), 'message' => "File $remote_file was deleted with FTP."));
      $json = json_encode($result->get());
      exit($json);
    }
  }
  /**
   * S2S request.
   */
  public function page_delete_remote_do(){
    $filename = wfRequest::get('filename');
    $filename = $this->replaceWebDir($filename);
    /**
     * Check IP.
     */
    $check_ip = $this->check_ip();
    $token_match = $this->token_match(wfRequest::get('token'));
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'check_ip' => $check_ip, 'token_match' => $token_match, 'filename' => $filename, 'message' => null, 'dir' => $this->dir));
    /**
     *
     */
    if(!$check_ip && !$token_match){
      $result->set('success', false);
      $result->set('message', 'Your ip or token is not registred!');
      exit(serialize($result->get()));
    }
    /**
     * Get content and put in serialize array.
     */
    if(wfFilesystem::fileExist($this->dir.$filename)){
      wfFilesystem::delete($this->dir.$filename);
      $result->set('exist', true);
    }else{
      $result->set('success', false);
      $result->set('exist', false);
      $result->set('message', 'File does not exist remote.');
    }
    exit(serialize($result->get()));
  }
  /**
   * S2C request.
   * Call this from local server.
   */
  public function page_delete_remote_folder(){
    $this->log('delete_remote_folder');
    $settings = $this->getSettings();
    if(!$settings->get('ftp')){
      $filename = wfRequest::get('key');
      $url = $this->getUrl('delete_remote_folder_do');
      $params = new PluginWfArray();
      $params->set('filename', $filename);
      $params->set('remote', $settings->get('remote'));
      $params->set('token', $settings->get('token'));
      wfPlugin::includeonce('server/push');
      $push = new PluginServerPush();
      $result = $push->push($url, $params->get());
      $unserialize = @unserialize($result);
      if($unserialize===false){
        exit($result);
      }else{
        exit(json_encode($unserialize));
      }
    }else{
      exit('Does not work if folder not empty...');
      $this->set_ftp();
      $filename = wfRequest::get('key');
      $remote_dir = $this->replaceWebDirFtp($filename);
      $bool = $this->ftp->rmdir($remote_dir);
      $result = new PluginWfArray(array('success' => $bool, 'files' => array($remote_dir), 'message' => "Dir $remote_dir was deleted with FTP."));
      $json = json_encode($result->get());
      exit($json);
    }
  }
  /**
   * S2S request.
   */
  public function page_delete_remote_folder_do(){
    $filename = wfRequest::get('filename');
    $filename = $this->replaceWebDir($filename);
    /**
     * Check IP.
     */
    $check_ip = $this->check_ip();
    $token_match = $this->token_match(wfRequest::get('token'));
    /**
     * Result.
     */
    $result = new PluginWfArray(array('success' => true, 'check_ip' => $check_ip, 'token_match' => $token_match, 'filename' => $filename, 'message' => null, 'dir' => $this->dir));
    /**
     *
     */
    if(!$check_ip && !$token_match){
      $result->set('success', false);
      $result->set('message', 'Your ip or token is not registred!');
      exit(serialize($result->get()));
    }
    /**
     * Get content and put in serialize array.
     */
    $dirname = $this->dir.$filename;
    if(wfFilesystem::fileExist($dirname)){
      wfFilesystem::delete_dir($dirname);
    }else{
      $result->set('success', false);
      $result->set('message', 'Folder does not exist remote.');
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
  private function get_sub_folder($folder_folder){
    return substr($folder_folder, 0, strpos($folder_folder, '/'));
  }
  private function handel_remote_get_url_origin($url){
    if(substr($url, strlen($url)-4)!='.git'){
      $url .= '.git';
    }
    return $url;
  }
  public function page_script(){
    $data = new PluginWfArray();
    $settings = $this->getSettings();
    /**
     * mkdir
     */
    $mkdir_buto = array();
    $mkdir_buto[] = "mkdir config";
    $mkdir_buto[] = "mkdir sys";
    $mkdir_buto[] = "mkdir web";
    $mkdir_buto[] = "mkdir web/plugin";
    $mkdir_buto[] = "mkdir web/theme";
    $mkdir_buto[] = "mkdir plugin";
    $mkdir_buto[] = "mkdir theme";
    /**
     * config
     */
    $config = array();
    $config[] = "touch config/settings.yml";
    $config[] = 'echo "# script generated file...\ntheme: '.$settings->get('theme').'" >> config/settings.yml';
    /**
     * sys
     */
    $sys = array();
    $sys[] = "git clone https://github.com/costlund/Buto-Sys-Mercury.git sys/mercury";
    $sys[] = "cp sys/mercury/root/* web";
    $sys[] = "cp sys/mercury/root/.htaccess web";
    /**
     * theme
     */
    $theme = array();
    $theme[] = "mkdir theme/".$this->get_sub_folder($settings->get('theme'));
    wfPlugin::includeonce('git/kbjr');
    $git = new PluginGitKbjr();
    $git->set_repo_theme($settings->get('theme'));
    if($git->exist()){
      $theme[] = "git clone ".$this->handel_remote_get_url_origin($git->remote_get_url_origin())." theme/".$settings->get('theme');
      $theme[] = "mkdir web/theme/".$this->get_sub_folder($settings->get('theme'));
      $theme[] = "mkdir web/theme/".$settings->get('theme');
      $theme[] = 'cp -R theme/'.$settings->get('theme').'/public/* web/theme/'.$settings->get('theme');
    }else{
      $theme[] = "# Could not find url for ".$settings->get('theme');
    }
    /**
     * mkdir_plugin
     */
    $mkdir_plugin = array();
    foreach ($settings->get('plugin') as $key => $value) {
      $i = new PluginWfArray($value);
      $mkdir_plugin[$this->get_sub_folder($i->get('name'))] = "mkdir plugin/".$this->get_sub_folder($i->get('name'));
    }
    /**
     * clone
     */
    $clone = array();
    foreach ($settings->get('plugin') as $key => $value) {
      $i = new PluginWfArray($value);
      if($i->get('git_url')){
        $clone[] = 'git clone '.$this->handel_remote_get_url_origin($i->get('git_url')).' plugin/'.$i->get('name');
      }else{
        $clone[] = '# '.$i->get('name').' has no git url.';
      }
    }
    /**
     * Public folder
     */
    $mkdir_web = array();
    $mkdir_web2 = array();
    $public = array();
    foreach ($settings->get('plugin') as $key => $value) {
      $i = new PluginWfArray($value);
      if($i->get('has_public')){
        $mkdir_web[$this->get_sub_folder($i->get('name'))] = "mkdir web/plugin/".$this->get_sub_folder($i->get('name'));
        $mkdir_web2[] = "mkdir web/plugin/".$i->get('name');
        $public[] = 'cp -R plugin/'.$i->get('name').'/public/* web/plugin/'.$i->get('name');
      }
    }
    /**
     * done
     */
    $done = array();
    $done[] = 'echo "Done..."';
    /**
     * 
     */
    $data = array('Make dir' => $mkdir_buto, 'Create config file' => $config, 'Buto system' => $sys, 'Theme' => $theme, 'Make plugin dir' => $mkdir_plugin, 'Clone plugin' => $clone, 'Make plugin web dir' => $mkdir_web, 'Make plugin sub web dir' => $mkdir_web2, 'Copy plugin public folders' => $public, 'Done' => $done);
    /**
     * 
     */
    $script = null;
    /**
     * 
     */
    $script .= "#########################################################\n";
    $script .= "# Create a folder and put this content in file pull.sh and run command \"sh pull.sh\" within the new folder.\n";
    $script .= "#########################################################\n";
    /**
     * 
     */
    foreach ($data as $key => $value) {
      $script .= "##########################################\n";
      $script .= "# ".$key." \n";
      $script .= "##########################################\n";
      foreach ($value as $key2 => $value2) {
        $script .= $value2."\n";
      }
    }
    wfHelp::textarea_dump($script);
  }
}
