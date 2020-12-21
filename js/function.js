function PluginLabSync(){
  this.btn_delete = null;
  this.files_count = null;
  this.file_number = null;
  this.start = function(type_of_sync){
    if(typeof type_of_sync == 'undefined'){
      type_of_sync = 'sync';
    }
    PluginBootstrapNavtabs_v1.nav_init({ul: 'my_navtabs', content: 'my_content', click: 1});
    PluginWfAjax.load('start', '/[[class]]/read/type_of_sync/'+type_of_sync);
    $('#sync_info').show();
}
  this.upload = function(btn){
    var local_newer = btn.parentNode.parentNode.getElementsByClassName('td_local_newer')[0];
    local_newer.innerHTML = '<img src="/plugin/wf/ajax/loading.gif">';
    $.get( "upload?key="+btn.getAttribute('data-file'), function( data ) {
      console.log(data);
      data = JSON.parse(data);
      if(data.success){
        local_newer.innerHTML = 'uploaded';
        PluginLabSync.sound();
        PluginLabSync.file_number ++;
        PluginLabSync.progress_set();
      }else{
        alert(data.message);
      }
    });
  }
  this.sound = function(){
    var audio = document.createElement("AUDIO");
    audio.src = '/plugin/play/sound/beep.mp3';
    audio.play();
  }
  this.delete_all_theme_no = function(btn){
    if(!confirm('Delete all visible where Theme has theme_no?')){
      return null;
    }
    var tds = document.getElementById('sync_table_wrapper').getElementsByClassName('td_theme');
    this.file_number = 0;
    this.files_count = tds.length;
    this.progress_set();
    for(i=0;i<tds.length;i++){
      if(tds[i].innerHTML=='(theme_no)'){
        var b = tds[i].parentNode.getElementsByClassName('btn_delete_all_theme_no')[0];
        b.onclick();
      }
    }
  }
  this.upload_all_localnewer = function(btn){
    if(!confirm('Upload all visible where Local newer is (localnewer)?')){
      return null;
    }
    var local_newer = document.getElementById('sync_table_wrapper').getElementsByClassName('td_local_newer');
    var count = 0;
    for(i=0;i<local_newer.length;i++){
      if(local_newer[i].innerHTML=='(local_newer)'){
        var b = local_newer[i].parentNode.getElementsByClassName('btn_upload')[0];
        b.onclick();
        count++;
      }
    }
    this.file_number = 0;
    this.files_count = i;
    this.progress_set();
    console.log(count+' files uploaded.');
  }
  this.upload_all_exist_local = function(btn){
    if(!confirm('Upload all visible where Exist is (local)?')){
      return null;
    }
    var local = document.getElementById('sync_table_wrapper').getElementsByClassName('td_exist');
    var count = 0;
    for(i=0;i<local.length;i++){
      if(local[i].innerHTML=='(local)'){
        var b = local[i].parentNode.getElementsByClassName('btn_upload')[0];
        b.onclick();
        count++;
      }
    }
    this.file_number = 0;
    this.files_count = i;
    this.progress_set();
    console.log(count+' files uploaded.');
  }
  this.progress_set = function(){
    document.getElementById('progress').innerHTML = this.file_number+' of '+this.files_count;
  }
  this.download = function(btn){
    var local_newer = btn.parentNode.parentNode.getElementsByClassName('td_local_newer')[0];
    local_newer.innerHTML = '<img src="/plugin/wf/ajax/loading.gif">';
    $.get( "download?key="+btn.getAttribute('data-file'), function( data ) {
      console.log(data);
      PluginLabSync.sound();
      data = JSON.parse(data);
      if(data.success){
        local_newer.innerHTML = 'downloaded';
      }else{
        alert(data.message);
      }
    });    
  }
  this.script = function(btn){
    PluginWfBootstrapjs.modal({id: 'modal_script', url: 'script', lable: 'Script'});
  }
  this.delete_form = function(btn){
    this.btn_delete = btn;
    PluginWfBootstrapjs.modal({id: 'modal_delete', content: '', lable: 'Delete'});
    var content = 
            [
      {type: 'p', innerHTML: [{type: 'strong', innerHTML: 'File: '},{type: 'span', innerHTML: decodeURIComponent(btn.getAttribute('data-file'))}]},
      {type: 'p', innerHTML: [{type: 'strong', innerHTML: 'Exist: '},{type: 'span', innerHTML: btn.getAttribute('data-exist')}]},
      {type: 'p', innerHTML: [
          {type: 'a', attribute: {class: 'btn btn-primary btn-sm',   id: 'btn_delete_local',         onclick: "PluginLabSync.delete('local')"},         innerHTML: 'Delete local'},
          {type: 'a', attribute: {class: 'btn btn-warning',          id: 'btn_delete_both',          onclick: "PluginLabSync.delete('both')"},          innerHTML: 'Delete both'},
          {type: 'a', attribute: {class: 'btn btn-primary btn-sm',   id: 'btn_delete_remote',        onclick: "PluginLabSync.delete('remote')"},        innerHTML: 'Delete remote'},
          {type: 'a', attribute: {class: 'btn btn-secondary btn-sm', id: 'btn_delete_remote_folder', onclick: "PluginLabSync.delete('remote_folder')"}, innerHTML: 'Delete remote folder'}
        ], attribute: {style: 'text-align:center'}}
            ];
    PluginWfDom.render(content, document.getElementById('modal_delete_body'));
    if(btn.getAttribute('data-exist')!='both'){
      document.getElementById('btn_delete_both').setAttribute('disabled', true);
      document.getElementById('btn_delete_both').setAttribute('onclick', null);
    }
    if(btn.getAttribute('data-exist')=='local'){
      document.getElementById('btn_delete_remote').setAttribute('disabled', true);
      document.getElementById('btn_delete_remote').setAttribute('onclick', null);
    }
    if(btn.getAttribute('data-exist')=='remote'){
      document.getElementById('btn_delete_local').setAttribute('disabled', true);
      document.getElementById('btn_delete_local').setAttribute('onclick', null);
    }
  }
  this.delete_remote = function(btn){
    var td = btn.parentNode.parentNode.getElementsByClassName('td_local_newer')[0];
    td.innerHTML = '<img src="/plugin/wf/ajax/loading.gif">';
    $.get( "delete_remote?key="+btn.getAttribute('data-file'), function( data ) {
      data = JSON.parse(data);
      if(data.success){
        td.innerHTML = 'Remote deleted';
        $('#modal_delete').modal('hide');
        PluginLabSync.sound();
        PluginLabSync.file_number ++;
        PluginLabSync.progress_set();
     }else{
        td.innerHTML = data.message;
      }
    });
  }
  this.delete = function(mode){
    var local_newer = this.btn_delete.parentNode.parentNode.getElementsByClassName('td_local_newer')[0];
    local_newer.innerHTML = '<img src="/plugin/wf/ajax/loading.gif">';
    if(mode=='local'){
      $.get( "delete_local?key="+this.btn_delete.getAttribute('data-file')+'&mode='+mode, function( data ) {
        data = JSON.parse(data);
        if(data.success){
          local_newer.innerHTML = 'Local deleted';
          $('#modal_delete').modal('hide');
        }else{
          local_newer.innerHTML = data.message;
        }
      });
    }else if(mode=='remote'){
      this.delete_remote(this.btn_delete);
    }else if(mode=='remote_folder'){
      var dir = prompt("Please enter your name", decodeURIComponent(this.btn_delete.getAttribute('data-dir')));
      if(dir){
        if(confirm('Are you sure to delete hole folder '+dir+'?')){
          $.get( "delete_remote_folder?key="+dir, function( data ) {
            data = JSON.parse(data);
            if(data.success){
              local_newer.innerHTML = 'Remote folder deleted';
              $('#modal_delete').modal('hide');
            }else{
              local_newer.innerHTML = data.message;
            }
          });
        }
      }
    }else if(mode=='both'){
      alert('not yet');
    }
  }
  this.zip = function(){
    window.open('zip');
  }
  this.theme_select = function(e){
    /**
     * Select a theme.
     */
    if(e.getAttribute('data-key')){
      $.getJSON( "theme_select?key="+e.getAttribute('data-key'), function( data ) {
        location.reload();
      });
    }else{
      $.getJSON( "theme_select", function( data ) {
        location.reload();
      });
    }
  }
}
var PluginLabSync = new PluginLabSync();







