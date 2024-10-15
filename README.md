## Personal Portfolio (Reno Philibert)

Welcome to my personal portfolio, a showcase of my work and skills as a Full Stack Software Engineer. This project is built with a modern tech stack, including Laravel 11, Vue 3, Inertia, and Tailwind to deliver a sleek, fast and responsive user experience. Here you'll find examples of my expertise in full-stack development including real-world applications, innovative UI/UX designs and custom solutions that highlight my passion for writing clean code and problem-solving. Dive in here and explore how I create beautiful web applications!

## Installation

To get this up and running locally, follow the steps below. The project is built using Laravel for the backend, Vue and Inertia for the frontend, then Tailwind CSS for styling. You'll need to have Docker Desktop, PHP 8.2+, Composer, Node.js and NPM installed on your machine to properly set up the environment. Once you've cloned the repository, you'll need to install the necessary dependencies and configure environment variables.

1. Clone the repository
  ```
  git clone git@github.com:rjp2525/reno.sh.git && cd reno.sh
  ```
2. Install PHP/JS dependencies
  ```
  composer install && npm i
  ```
3. Copy the `.env.example` sample environment variable file to `.env` and set necessary keys
  ```
  cp .env.example .env
  php artisan key:generate
  nano .env
  ```
  ```env
  # TODO: add env variables that require changing
  ```
4. Start the Docker container and run migrations
  ```
  ./vendor/bin/sail up -d
  ./vendor/bin/sail artisan migrate --seed
  ```
5. Start Vite for compiling frontend assets
  ```
  npm run dev
  ```
6. You should now be able to visit [localhost](http://localhost) in your browser.

## Built with

This project is built with the following technologies and packages:

- [Laravel](https://laravel.com/) - A powerful PHP framework that simplifies backend development with elegant syntax and robust features.
- [Vue.js](https://vuejs.org/) - A progressive JavaScript framework used to build interactive user interfaces with reactive components.
- [Inertia.js](https://inertiajs.com/) - A routing library that allows building single-page applications without the need for a separate API, seamlessly connecting Laravel and Vue.
- [Tailwind CSS](https://tailwindcss.com/) - A utility-first CSS framework that provides a highly customizable styling system for rapid UI development.
- [Filament](https://filamentphp.com/) - A lightweight and elegant admin panel for Laravel, used to quickly build back-end interfaces for managing content and data with ease.
