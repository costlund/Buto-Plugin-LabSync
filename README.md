# Buto-Plugin-LabSync
Sync files from a web browser instead of using FTP desktop client.

Takes all files or files for a theme via parameter filter/theme.

Keep track of:
- Files only local.
- Newer files local against server.
- Files only on server.

Buttons to batch sync files.

## Admin layout
Param admin_layout is optional.

## Path string
Params url and local_time can have yml path string.

## IP
Param ip is to protect when remote sync.

## Theme
Optional param filter/theme is if only sync one theme and all it´ dependencies.

## Item
Param filter/item is when not param filter/theme is in usage.

## Settings
```
plugin_modules:
  sync:
    plugin: 'lab/sync'
    settings:
      admin_layout: /theme/[theme]/layout/main.yml
      url: 'https://_url_to_remote_sync_/sync'
      filter:
        theme: dev/theme
        item:
          -
            value: '/sys/*'
          -
            value: '/plugin/*'
          -
            value: '/[web_folder]/*'
          -
            value: '/theme/*'
      ip:
        - 127.0.0.1
      local_time: '2001-01-01 00:00:00'
```

## Extra folders
One could add extra folders for a theme via parameter external_folders.
```
plugin:
  lab:
    sync:
      data:
        external_folders:
          - '/[web_folder]/_any_folder_/*'
```

## ZIP export
One could export a theme to a zip file.
Files and folder start with "." except ".htaccess" are not included. If linked folders has errors warnings a zip file are corrupted.
