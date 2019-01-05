function PluginLabSync(){
  this.btn_delete = null;
  this.upload = function(btn){
    var local_newer = btn.parentNode.parentNode.getElementsByClassName('td_local_newer')[0];
    local_newer.innerHTML = '<img src="/plugin/wf/ajax/loading.gif">';
    $.get( "upload?key="+btn.getAttribute('data-file'), function( data ) {
      console.log(data);
      data = JSON.parse(data);
      if(data.success){
        var span = btn.getElementsByClassName('glyphicon')[0];
        span.style.color='green';
        $(span).removeClass('glyphicon-upload');
        $(span).addClass('glyphicon-cloud-upload');
        local_newer.innerHTML = 'uploaded';
      }else{
        alert(data.message);
      }
    });    
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
    console.log(count+' files uploaded.');
  }
  this.download = function(btn){
    var local_newer = btn.parentNode.parentNode.getElementsByClassName('td_local_newer')[0];
    local_newer.innerHTML = '<img src="/plugin/wf/ajax/loading.gif">';
    $.get( "download?key="+btn.getAttribute('data-file'), function( data ) {
      console.log(data);
      data = JSON.parse(data);
      if(data.success){
        var span = btn.getElementsByClassName('glyphicon')[0];
        span.style.color='green';
        $(span).removeClass('glyphicon-upload');
        $(span).addClass('glyphicon-cloud-upload');
        local_newer.innerHTML = 'downloaded';
      }else{
        alert(data.message);
      }
    });    
  }
  this.delete_form = function(btn){
    this.btn_delete = btn;
    PluginWfBootstrapjs.modal({id: 'modal_delete', content: '', lable: 'Delete'});
    var content = 
            [
      {type: 'p', innerHTML: [{type: 'strong', innerHTML: 'File: '},{type: 'span', innerHTML: decodeURIComponent(btn.getAttribute('data-file'))}]},
      {type: 'p', innerHTML: [{type: 'strong', innerHTML: 'Exist: '},{type: 'span', innerHTML: btn.getAttribute('data-exist')}]},
      {type: 'p', innerHTML: [
          {type: 'a', attribute: {class: 'btn btn-primary btn-sm', id: 'btn_delete_local', onclick: "PluginLabSync.delete('local')"}, innerHTML: 'Delete local'},
          {type: 'a', attribute: {class: 'btn btn-warning', id: 'btn_delete_both', onclick: "PluginLabSync.delete('both')"}, innerHTML: 'Delete both'},
          {type: 'a', attribute: {class: 'btn btn-primary btn-sm', id: 'btn_delete_remote', onclick: "PluginLabSync.delete('remote')"}, innerHTML: 'Delete remote'}
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
      $.get( "delete_remote?key="+this.btn_delete.getAttribute('data-file'), function( data ) {
        data = JSON.parse(data);
        if(data.success){
          local_newer.innerHTML = 'Remote deleted';
          $('#modal_delete').modal('hide');
        }else{
          local_newer.innerHTML = data.message;
        }
      });
    }else if(mode=='both'){
      alert('not yet');
    }
  }
  this.zip = function(){
    window.open('zip');
  }
}
var PluginLabSync = new PluginLabSync();







