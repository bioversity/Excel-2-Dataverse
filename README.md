### Excel 2 Dataverse

This script parse an Excel file and convert all data for Dataverse edit/update.

### Installation
Note: This script need a webserver to run, so you can create a local domain to use every time you need.

##### Requirements
PHP version 7.2 with the following modules:
* php7.2-curl
* php7.2-dev
* php7.2-json
* php7.2-xml
* php7.2-zip

``` shell
$ apt-get install php7.2 php7.2-common php7.2-curl php7.2-dev php7.2-json php7.2-xml php7.2-zip
```

##### Clone or download this repository:

```bash
$ git clone https://github.com/bioversity/Excel-2-Dataverse.git
```

##### Create a local domain

If you want to leave the `localhost` address free, you can set a new local domain by appending this line in the `/etc/hosts`:

```config
127.0.1.1               excel2dataverse.local
```

##### Setup the webserver

Following sample configurations:

###### Apache

```config

NameVirtualHost excel2dataverse.local:80

<VirtualHost excel2dataverse.local:80>
        ServerName excel2dataverse.local
        ServerAdmin webmaster@localhost

        #LogLevel info ssl:warn

        ErrorLog ${APACHE_LOG_DIR}/error.excel2dataverse.log
        CustomLog ${APACHE_LOG_DIR}/access.excel2dataverse.log combined

        #Include conf-available/serve-cgi-bin.conf

        DocumentRoot /var/www/bioversity/excel2dataverse
        <Directory /var/www/bioversity/excel2dataverse>
            Options Indexes FollowSymLinks MultiViews
            AllowOverride All
            Order allow,deny
            allow from all
        </Directory>
</VirtualHost>

```

###### Nginx

```config
server {
    listen 80;
    server_name excel2dataverse.local;
    root /var/www/bioversity/excel2dataverse;
}

```

### Run

When launched the script check previously exported files in the directory `export`, if not present it generates.

The script accept GET parameters, so you can play with the address bar adding the following parameters:
* `row`: **Row filter**<br />Use this parameter to filter rows. Values can be a single row number (eg. `2`), a list of rows (eg: `2,3,4,10`) or a range (eg. `2-10`\).<br />__Note__: The first row is used for column titles so is not available.<br />Check the output statistics in the `rows > filter` section.
* `debug`: **Debug mode**<br />In debug mode no files will be generated and also append "old_values" into the output tree
* `only_fields`: **Only fields tree**<br />With this parameter the script generates only the tree with data present in `dataset > results > data > latestVersion > metadataBlocks > citation > fields`

Example commands:

[`http://excel2dataverse.local/`](http://excel2dataverse.local/)<br />This address generates the `output.json` and `output.txt` of the entire excel file.

[`http://dataverse.local/?row=2`](http://excel2dataverse.local/?row=2)<br />This address display and save the output only for row 2.

[`http://excel2dataverse.local/?debug&only_fields&row=2-10`](http://excel2dataverse.local/?debug&only_fields&row=2-10)<br />This address display the output of rows from 2 to 10, with only the fields section and without saving the output locally.
