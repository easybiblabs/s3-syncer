## Upload/Sync local files to Amazon S3
_upload satis generated package files to S3-bucket_

### Installation
run ``composer install``inside this directory to install dependencies via Composer

### Usage
once installed the S3-syncer can be used by executing bin/syncer

to upload the contents of local folder to an S3 bucket run the syncer command with the following parameters: ``$ bin/syncer sync bucketname directoryname``
