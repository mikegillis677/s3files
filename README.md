# S3Files
A simple Symfony 4 web app for sharing files from an AWS S3 bucket with branded download pages.

Authentication to upload files is done by Google OAuth using a G Suite email domain.
Authorization to upload files is done by Google Groups using a G Suite email domain.

Files are stored 

Some information about the uploaded files along with the email address of who uploaded the file is stored in a database.

Configuration is done with Symfony 4 .env and .env.local files.

To brand the download pages, edit templates/public/default.twig
