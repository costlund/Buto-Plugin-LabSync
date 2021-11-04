# Sync server
This document describes how to use this plugin as a sync server (lets say sync.world.com for example) for multiple domains on your hosting partner.

## Server settings
Set your ip and point where to remote data is located.
```
plugin_modules:
  sync:
    plugin: 'lab/sync'
    settings:
      ip:
        - _your_ip_from_where_to_sync_
      remote: 'yml:/../buto_data/theme/_my_/_theme_/plugin_lab_sync.yml:remote'
      token: 'yml:/../buto_data/theme/_my_/_theme_/plugin_lab_sync.yml:token'
```
In file /../buto_data/theme/_my_/_theme_/plugin_lab_sync.yml we add param remote and token.
```
remote:
  abcdef:
    dir: /_my_domain_folder_
token:
  -
    value: 123456
```

## Local settings
Theme dev/theme.
On your local dev/theme client in file /../buto_data/theme/_my_/_theme_/plugin_lab_sync.yml we add param theme.
```
theme:
  -
    name: My production site
    theme: _any_/_theme_
    url: 'https://sync.world.com/sync'
    remote: 'abcdef'
    token: 123456
```


