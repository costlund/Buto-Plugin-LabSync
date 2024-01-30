# Buto-Plugin-LabSync

<p>Sync files between development host and production host using rsync, zip, ftp or http.</p>

<a name="key_0"></a>

## Settings



<a name="key_0_0"></a>

### Theme

<ul>
<li>Param admin_layout is optional.</li>
<li>Theme could be yml data or file.</li>
<li>Param ip is to protect this page by ip number (only http method).</li>
<li>Param data_file is to store ip for last sign in by a webmaster account (only http method). One has to set the signin event to run this.</li>
</ul>
<pre><code>plugin_modules:
  sync:
    plugin: 'lab/sync'
    settings:
      admin_layout: /theme/[theme]/layout/main_bs4.yml
      theme: 'yml:/../buto_data/theme/[theme]/plugin_lab_sync.yml:theme'
      ip:
        - 127.0.0.1
        - '::1'
      data_file: '/../buto_data/theme/my/theme/plugin_lab_sync.yml'</code></pre>
<p>Extra folders.
In theme /config/settings.yml one could add extra folders for a theme via parameter external_folders.</p>
<pre><code>plugin:
  lab:
    sync:
      data:
        external_folders:
          - '/[web_folder]/_any_folder_/*'</code></pre>
<pre><code>exclude:
  - '/[web_folder]/data'</code></pre>

<a name="key_0_1"></a>

### plugin_lab_sync.yml

<ul>
<li>Set param local_time if files on development host are newer than production host because of copy issue.</li>
<li>One could exclude files and folders.</li>
</ul>
<pre><code>theme:
  -
    name: 'My home page'
    local_time: '2019-10-09 16:32:44'
    theme: _my_/_theme_
    exclude:
      - /[web_folder]/data/*
    ftp: ...</code></pre>

<a name="key_0_2"></a>

### FTP

<ul>
<li>Param ftp is for using the ftp method.</li>
<li>Param dir should be empty if ftp account are restricted to the application folder. Otherwice set the folder.</li>
<li>Param web_folder must be set to the Apache root folder.</li>
</ul>
<pre><code>theme:
  -
    ftp:
      server: ftp.world.com
      user: _user_
      password: _password_
      dir: '/_app_folder_'
      web_folder: public_html</code></pre>

<a name="key_0_3"></a>

### HTTP

<p>Set the url param to where to sync with http.</p>
<pre><code>theme:
  -
    url: 'http://skaf.demo.stenbergit.net/sync'</code></pre>

<a name="key_0_4"></a>

### ZIP

<ul>
<li>One could export a theme to a zip file.</li>
<li>One has to set param zip/folder.</li>
<li>Files and folder start with "." except ".htaccess" are not included. </li>
<li>If linked folders has errors warnings the zip file is going to be corrupted.</li>
</ul>
<p>Add extra config parameters to file /config/settings.yml.</p>
<pre><code>    zip:
      config:
        tag: _tag_
      folder: /my/folder</code></pre>
<p>Signin event.
Event settings is for add webmaster ip to server along with settings/ip.</p>
<pre><code>events:
  signin:
    -
      plugin: 'lab/sync'
      method: 'signin'</code></pre>

<a name="key_1"></a>

## Usage



<a name="key_1_0"></a>

### Export

<ul>
<li>Export theme to a folder.</li>
<li>Exclude files with the exclude param in theme settings.</li>
<li>Use rsync script generated to deploy to server.</li>
</ul>
<p>Flow in export folder.</p>
<ul>
<li>Use the Export button in menu to trigger this.</li>
<li>Delete all folder but not hiddens like .git folder.</li>
<li>Copy theme and plugins to folder.</li>
</ul>
<pre><code>    export:</code></pre>
<p>Folder to copy files only used by current theme.</p>
<pre><code>      folder: /my/export/folder</code></pre>
<p>File /config/settings.yml will be created.
Add extra config parameters.</p>
<pre><code>      config:
        tag: _tag_</code></pre>
<p>Web folder.
If remote host has other name for web folder.
If not set current web folder is used.</p>
<pre><code>      web_folder: public_html</code></pre>
<p>Rsync remote.
If this param is set a new param export/rsync_script will be generated with full rsync script.</p>
<pre><code>      rsync_remote: me@world.com:/var/www/html</code></pre>
<p>Param export/rsync_script generated.</p>
<pre><code>      rsync_script: rsync -azv --delete -e ssh /my/export/folder/ me@world.com:/var/www/html</code></pre>
<p>Rsync exclude.
Exclude params are generated from theme settings exclude params.</p>

<a name="key_2"></a>

## Pages



<a name="key_3"></a>

## Widgets



<a name="key_4"></a>

## Methods



