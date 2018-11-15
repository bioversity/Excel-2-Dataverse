### AGROVOC Indexing

This script parse an Excel file and convert all data for Dataverse edit/update.

### Installation

Note: PHP need a webserver to run.

Clone or download this repository:

```bash
$ git clone https://github.com/gubi/bioversity_agrovoc-indexing.git
```

Run on your local server.

##### Create a local domain

If you want to leave the `localhost` address free, you can set a new local domain by appending this line in the `/etc/hosts`:

```config
127.0.1.1               dataverse.local
```

##### Setup the webserver

Following a sample couple configurations:

###### Apache

```config

NameVirtualHost dataverse.local:80

<VirtualHost dataverse.local:80>
        ServerName dataverse.local
        ServerAdmin webmaster@localhost

        #LogLevel info ssl:warn

        ErrorLog ${APACHE_LOG_DIR}/error.dataverse.log
        CustomLog ${APACHE_LOG_DIR}/access.dataverse.log combined

        #Include conf-available/serve-cgi-bin.conf

        DocumentRoot /var/www/bioversity/dataverse
        <Directory /var/www/bioversity/dataverse>
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
    server_name dataverse.local;
    root /var/www/bioversity/dataverse;
}

```

### Run

When launched the script check previously exported files in the directory `export`, if not present it generates.

The script accept GET parameters, so you can play with the address bar adding the following parameters:* `row`: **Row filter**<br />Use this parameter to filter rows. Values can be a single row number (eg. `2`), a list of rows (eg: `2,3,4,10`) or a range (eg. `2-10`\)* `debug`: **Debug mode**<br />In debug mode no files will be generated and also append "old_values" into the output tree* `only_fields`: **Only fields tree**<br />With this parameter the script generates only the tree with data present in `dataset > results > data > latestVersion > metadataBlocks > citation > fields`

Example commands:

[`http://dataverse.local/`](http://dataverse.local/)<br />This address generates the `output.json` and `output.txt`, with all rows of the excel file.

[`http://dataverse.local/?row=50,51`](http://dataverse.local/?row=50,51)<br />This address display and save the output only for rows 50 and 51.

[`http://dataverse.local/?debug&only_fields&row=3-5`](http://dataverse.local/?debug&only_fields&row=3-5)<br />This address display the output of rows from 3 to 5, with only the fields section and without saving the output locally.
