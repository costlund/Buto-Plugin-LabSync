# Buto-Plugin-LabSync
Sync files between development host and production host using ftp or http.
## Settings.yml
- Param admin_layout is optional.
- Theme could be yml data or file.
- Param ip is to protect this page by ip number (only http method).
- Param data_file is to store ip for last sign in by a webmaster account (only http method). One has to set the signin event to run this.

```
plugin_modules:
  sync:
    plugin: 'lab/sync'
    settings:
      admin_layout: /theme/[theme]/layout/main_bs4.yml
      theme: 'yml:/../buto_data/theme/[theme]/plugin_lab_sync.yml:theme'
      ip:
        - 127.0.0.1
        - '::1'
      data_file: '/../buto_data/theme/my/theme/plugin_lab_sync.yml'
```
## plugin_lab_sync.yml.
- Set param local_time if files on development host are newer than production host because of copy issue.
- One could exclude files and folders.

```
theme:
  -
    name: 'My home page'
    local_time: '2019-10-09 16:32:44'
    theme: _my_/_theme_
    exclude:
      - /[web_folder]/data/*
    ftp: ...
```

### FTP
- Param ftp is for using the ftp method.
- Param dir should be empty if ftp account are restricted to the application folder. Otherwice set the folder.
- Param web_folder must be set to the Apache root folder.

```
theme:
  -
    ftp:
      server: ftp.world.com
      user: _user_
      password: _password_
      dir: '/_app_folder_'
      web_folder: public_html
```
### HTTP
Set the url param to where to sync with http.
```
theme:
  -
    url: 'http://skaf.demo.stenbergit.net/sync'
```
## Extra folders
In theme /config/settings.yml one could add extra folders for a theme via parameter external_folders.
```
plugin:
  lab:
    sync:
      data:
        external_folders:
          - '/[web_folder]/_any_folder_/*'
```
## ZIP export
One could export a theme to a zip file. The file will be created in app root folder and also to be downloaded in browser.
Files and folder start with "." except ".htaccess" are not included. If linked folders has errors warnings a zip file are corrupted.

### Config
Add extra config parameters to file /config/settings.yml.
```
    zip:
      config:
        tag: _tag_
```

## Export
- Export theme to a folder.
- Use the Export button in menu to trigger this.
- Use rsync script generated to deploy to server.

```
    export:
```

### Folder
Folder to copy files only used by current theme.
```
      folder: /my/export/folder
```

#### Config
File /config/settings.yml will be created.
Add extra config parameters.
```
      config:
        tag: _tag_
```

### Web folder
If remote host has other name for web folder.
If not set current web folder is used.
```
      web_folder: public_html
```

### Rsync remote
If this param is set a new param export/rsync_script will be generated with full rsync script.
```
      rsync_remote: me@world.com:/var/www/html
```
Param export/rsync_script generated.
```
      rsync_script: rsync -azv --delete -e ssh /my/export/folder/ me@world.com:/var/www/html
```

### Rsync exclude
Exclude params are generated from theme settings exclude params.
#### Theme settings
```
exclude:
  - '/[web_folder]/data'
```
#### Script
```
      rsync_script: rsync -azv --exclude "/public_html/data" --delete -e ssh /my/export/folder/ me@world.com:/var/www/html
```

## Signin event
Event settings is for add webmaster ip to server along with settings/ip.
```
events:
  signin:
    -
      plugin: 'lab/sync'
      method: 'signin'
```
