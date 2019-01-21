# Buto-Plugin-LabSync
Sync files from a web browser instead of using FTP desktop client.

Takes all files or files for a theme via parameter filter/theme.



## ZIP export

One could add extra folders for a theme via parameter external_folders.

```
plugin:
  lab:
    sync:
      data:
        external_folders:
          - '/[web_folder]/_any_folder_/*'
```



Files and folder start with "." except ".htaccess" are not included.

