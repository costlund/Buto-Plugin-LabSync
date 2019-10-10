# Buto-Plugin-LabSync

Sync files between client and server. This software handle both the client part and server part.

First time one has to use a FTP client (or other method) to upload files. Then one could use this software to sync file only for a specific theme. Other files will not be involved.



## Settings

Param ip is for secure validation when push files to server. This one is IMPORTANT on the remote server settings. Use param exclude for files not has to be sync to server.

### Client

```
plugin_modules:
  sync:
    plugin: 'lab/sync'
    settings:
      admin_layout: /theme/[theme]/layout/main.yml
      theme:
        -
          name: My domain
          url: 'https://www._my_domain_.com/sync'
          local_time: '2018-01-01 22:33:44'
          theme: my/theme
          exclude:
            - /theme/my/theme/version/*
```

### FTP

FTP settings.

```
plugin_modules:
  sync:
    plugin: 'lab/sync'
    settings:
    ftp:
      server: _
      user: _
      password: _
      dir: /_buto_app_folder_
      web_folder: public_html
```


Param theme can also have string to yml.

### Server

```
plugin_modules:
  sync:
    plugin: 'lab/sync'
    settings:
      ip:
        - 127.0.0.1
        - '::1'
      data_file: '/../buto_data/theme/my/theme/plugin_lab_sync.yml'
```

Event settings is for add webmaster ip to server along with settings/ip.

```
events:
  signin:
    -
      plugin: 'lab/sync'
      method: 'signin'
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

One could export a theme to a zip file. The file will be created in theme root folder and also to be downloaded in browser.
Files and folder start with "." except ".htaccess" are not included. If linked folders has errors warnings a zip file are corrupted.
