# Upload/Sync local files to Amazon S3

_Break free of Github Downloads and upload satis generated package files to Amazon-S3-bucket._

## Requirements

 * PHP 5.3+
 * [composer/satis](https://github.com/composer/satis)
 * [Amazon S3](http://aws.amazon.com/s3/)

## Installation

Run ``composer install``inside this directory to install the dependencies via Composer.

## Usage

Once installed the S3-syncer can be used by executing `./bin/syncer`.

To upload the contents of local folder to an Amazon S3 bucket run the syncer command with the following parameters: 

    $ ./bin/syncer sync path-to-satis.json satis-output-directory

When using the ``--dry`` flag no files are uploaded to Amazon S3.
