<?php

namespace controllers;



use models\EventTable;

use models\BlogTable;

use models\SubscriberTable;

// ADDED: persistent image storage for the Vercel deployment.

use framework\BlobStorage;

use framework\EmailService;



/*

 * AdminController

 *

 * Handles admin-related pages and actions for event and blog management.

 * The controller checks admin permissions, communicates with the model layer,

 * manages uploaded event images, and sends new-event notifications to subscribers.

 */

class AdminController

{

    /* Models and services used by the admin dashboard */

    private EventTable $events;

    private BlogTable $blogPosts;

    private SubscriberTable $subscribers;

    private EmailService $emailService;

    // ADDED: persistent storage service used for event image uploads.
    private BlobStorage $blobStorage;

    /* Initialise models and services when the controller is created */

    public function __construct()

    {

        $this->events = new EventTable();

        $this->blogPosts = new BlogTable();

        $this->subscribers = new SubscriberTable();

        $this->emailService = new EmailService();

        // ADDED: initialise persistent Vercel Blob storage.
        $this->blobStorage = new BlobStorage();

    }



    /* Display the admin dashboard with events and blog posts */

    public function index(): array

    {

        /* Ensure the current user has admin privileges */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Retrieve all events and blog posts from the database */

        $eventList = $this->events->findAll();

        $postList = $this->blogPosts->findAll();



        /* Return the admin view with event and blog data */

        return [

            'title' => 'Admin',

            'template' => 'admin.html.php',

            'styles' => ['admin.css'],

            'scripts' => ['admin.js'],

            'variables' => [

                'events' => $eventList,

                'posts' => $postList

            ]

        ];

    }



    /* Detect whether the request came from JavaScript fetch/AJAX. */

    private function isAjaxRequest(): bool

    {

        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&

            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    }



    /* Send JSON output and stop further rendering. */

    private function jsonResponse(array $data, int $statusCode = 200): void

    {

        http_response_code($statusCode);

        header('Content-Type: application/json');

        echo json_encode($data);

        exit;

    }



    /* Verify that the user is logged in and has the admin role */

    private function requireAdmin(): ?array

    {

        if (empty($_SESSION['loggedIn']) || empty($_SESSION['user'])) {

            $_SESSION['flash_message'] = 'Please log in to access that page.';

            $_SESSION['flash_type'] = 'error';



            return ['redirect' => '/account'];

        }



        if ($_SESSION['user']['role'] !== 'admin') {

            $_SESSION['flash_message'] = 'You do not have permission to access the admin area.';

            $_SESSION['flash_type'] = 'error';



            return ['redirect' => '/home'];

        }



        return null;

    }



    /* Render the create event form for administrators */

    public function create(): ?array

    {

        /* Ensure only admins can access the form */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Load a JavaScript file to submit the form asynchronously. */

        return [

            'title' => 'Create Event',

            'template' => 'createEvent.html.php',

            'styles' => ['admin-form.css'],

            'scripts' => ['createEvent.js'],

            'variables' => []

        ];

    }



    /* Process the create event form submission */

    public function store(): ?array

    {

        /* Ensure only admins can create events */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Retrieve form values from POST request */

        $event_title = trim($_POST['title'] ?? '');

        $event_type = trim($_POST['event_type'] ?? '');

        $category = trim($_POST['category'] ?? '');

        $location = trim($_POST['location'] ?? '');

        $description = trim($_POST['description'] ?? '');



        /* Convert input date into database datetime format */

        $date = new \DateTime($_POST['event_date']);

        $event_date = $date->format('Y-m-d H:i:s');



        /* Default image used when no upload is provided */

        $imagePath = null;



        /* Handle optional image upload */

        // CHANGED: store uploaded images in persistent Vercel Blob storage
        // instead of the container filesystem.
        if (!empty($_FILES['image']['name'])) {
            try {
                $imagePath = $this->blobStorage->uploadEventImage(
                    $_FILES['image']
                );
            } catch (\RuntimeException $e) {
                return $this->storeError($e->getMessage());
            }
        }


        /* Save the new event and keep the generated ID */

        $eventId = $this->events->save([

            'title' => $event_title,

            'event_type' => $event_type,

            'category' => $category,

            'event_date' => $event_date,

            'location' => $location,

            'description' => $description,

            'image_path' => $imagePath

        ]);



        /* Retrieve the saved event and all active subscribers */

        $newEvent = $this->events->findById((int) $eventId);

        $activeSubscribers = $this->subscribers->findActive();



        /* Notify newsletter subscribers that a new event has been added */

        if ($newEvent) {

            foreach ($activeSubscribers as $subscriber) {

                $this->emailService->sendNewEventNotification(

                    $subscriber->email,

                    $newEvent

                );

            }

        }



        /* Return JSON if the form was submitted using AJAX */

        if ($this->isAjaxRequest()) {

            $this->jsonResponse([

                'success' => true,

                'message' => 'Event created successfully.'

            ]);

        }



        /* Store success feedback for normal form submissions */

        $_SESSION['flash_message'] = 'Event created successfully.';

        $_SESSION['flash_type'] = 'success';



        /* Fallback redirect for normal form submissions */

        return ['redirect' => '/admin'];

    }



    /* Display the edit event form */

    public function edit(): ?array

    {

        /* Ensure only admins can edit events */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Get event ID from the URL */

        $id = $_GET['id'] ?? null;



        /* Redirect if no ID was provided */

        if (!$id) {

            $_SESSION['flash_message'] = 'Event not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Retrieve the selected event from the database */

        $event = $this->events->findById((int) $id);



        /* Redirect if event does not exist */

        if (!$event) {

            $_SESSION['flash_message'] = 'Event not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Load a JavaScript file to submit the edit form asynchronously. */

        return [

            'title' => 'Edit Event',

            'template' => 'editEvent.html.php',

            'styles' => ['admin-form.css'],

            'scripts' => ['editEvent.js'],

            'variables' => [

                'event' => $event

            ]

        ];

    }



    /* Handle submission of edited event data */

    public function update(): ?array

    {

        /* Ensure only admins can update events */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Get event ID from POST data */

        $eventid = (int) ($_POST['eventid'] ?? 0);



        /* Redirect if the event ID is missing */

        if (!$eventid) {

            $_SESSION['flash_message'] = 'Event not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Retrieve the existing event before updating */

        $existingEvent = $this->events->findById($eventid);



        /* Redirect if the event does not exist */

        if (!$existingEvent) {

            $_SESSION['flash_message'] = 'Event not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Retrieve updated form values from POST request */

        $event_title = trim($_POST['title'] ?? '');

        $event_type = trim($_POST['event_type'] ?? '');

        $category = trim($_POST['category'] ?? '');

        $location = trim($_POST['location'] ?? '');

        $description = trim($_POST['description'] ?? '');



        /* Convert input date into database datetime format */

        $date = new \DateTime($_POST['event_date']);

        $event_date = $date->format('Y-m-d H:i:s');



        /* Keep the existing image unless a new one is uploaded */

        $imagePath = $existingEvent->image_path;



        /* Handle optional replacement image upload */

        // CHANGED: upload a replacement image to persistent Vercel Blob storage.
        // If no new file is selected, keep the existing image URL.
        if (!empty($_FILES['image']['name'])) {
            try {
                $imagePath = $this->blobStorage->uploadEventImage(
                    $_FILES['image']
                );
            } catch (\RuntimeException $e) {
                return [
                    'title' => 'Edit Event',
                    'template' => 'editEvent.html.php',
                    'styles' => ['admin-form.css'],
                    'scripts' => ['editEvent.js'],
                    'variables' => [
                        'event' => $existingEvent,
                        'error' => $e->getMessage()
                    ]
                ];
            }
        }


        /* Save the updated event using the model */

        $this->events->save([

            'eventid' => $eventid,

            'title' => $event_title,

            'event_type' => $event_type,

            'category' => $category,

            'event_date' => $event_date,

            'location' => $location,

            'description' => $description,

            'image_path' => $imagePath

        ]);



        /* Return JSON if the form was submitted using AJAX */

        if ($this->isAjaxRequest()) {

            $this->jsonResponse([

                'success' => true,

                'message' => 'Event updated successfully.'

            ]);

        }



        /* Store success feedback for normal form submissions */

        $_SESSION['flash_message'] = 'Event updated successfully.';

        $_SESSION['flash_type'] = 'success';



        /* Fallback redirect for normal form submissions */

        return ['redirect' => '/admin'];

    }



    /* Delete an event selected by the administrator */

    public function delete(): ?array

    {

        /* Ensure only admins can delete events */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Get event ID from URL */

        $id = $_GET['id'] ?? null;



        /* Redirect if ID is missing */

        if (!$id) {

            $_SESSION['flash_message'] = 'Event not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Confirm event exists before deletion */

        $event = $this->events->findById((int) $id);



        /* Redirect if event does not exist */

        if (!$event) {

            $_SESSION['flash_message'] = 'Event not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Remove event from the database */

        $this->events->delete((int) $id);



        /* Store success feedback after deletion */

        $_SESSION['flash_message'] = 'Event deleted successfully.';

        $_SESSION['flash_type'] = 'success';



        /* Redirect back to the admin page */

        return ['redirect' => '/admin'];

    }



    /* Return create form error as JSON for AJAX requests,

       or fall back to a normal page render for non-JS users. */

    private function storeError(string $message): array

    {

        if ($this->isAjaxRequest()) {

            $this->jsonResponse([

                'success' => false,

                'message' => $message

            ], 422);

        }



        return [

            'title' => 'Create Event',

            'template' => 'createEvent.html.php',

            'styles' => ['admin-form.css'],

            'scripts' => ['createEvent.js'],

            'variables' => [

                'error' => $message

            ]

        ];

    }



    /* Search for events matching a term and return results as JSON. */

    public function search(): void

    {

        if ($result = $this->requireAdmin()) {

            http_response_code(403);

            header('Content-Type: application/json');

            echo json_encode([

                'success' => false,

                'message' => 'Unauthorized'

            ]);

            exit;

        }



        /* Get the search term from the query string */

        $term = trim($_GET['q'] ?? '');



        /* Retrieve either all events or matching events */

        $events = $term === ''

            ? $this->events->findAll()

            : $this->events->adminSearch($term);



        /* Return matching events as JSON for the admin AJAX search */

        header('Content-Type: application/json');

        echo json_encode([

            'success' => true,

            'events' => $events

        ]);

        exit;

    }



    /* Render the create blog post form for administrators */

    public function createBlog(): array

    {

        /* Ensure only admins can access the form */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Return view for creating a new blog post */

        return [

            'title' => 'Create Blog Post',

            'template' => 'createBlog.html.php',

            'styles' => ['admin-form.css'],

            'variables' => []

        ];

    }



    /* Process the create blog post form submission */

    public function storeBlog(): array

    {

        /* Ensure only admins can create blog posts */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Retrieve form values from POST request */

        $title = trim($_POST['title'] ?? '');

        $category = trim($_POST['category'] ?? '');

        $content = trim($_POST['content'] ?? '');



        /* Return validation error if required fields are missing */

        if ($title === '' || $category === '' || $content === '') {

            return [

                'title' => 'Create Blog Post',

                'template' => 'createBlog.html.php',

                'styles' => ['admin-form.css'],

                'variables' => [

                    'error' => 'All fields are required.'

                ]

            ];

        }



        /* Save the new blog post using the model */

        $this->blogPosts->save([

            'title' => $title,

            'category' => $category,

            'content' => $content

        ]);



        /* Store success feedback after creation */

        $_SESSION['flash_message'] = 'Blog post created successfully.';

        $_SESSION['flash_type'] = 'success';



        /* Redirect back to the admin dashboard */

        return ['redirect' => '/admin'];

    }



    /* Display the edit blog post form */

    public function editBlog(): array

    {

        /* Ensure only admins can edit blog posts */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Get blog post ID from the URL */

        $id = $_GET['id'] ?? null;



        /* Redirect if no blog post ID was provided */

        if (!$id) {

            $_SESSION['flash_message'] = 'Blog post not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Retrieve the selected blog post from the database */

        $post = $this->blogPosts->findById((int) $id);



        /* Redirect if the blog post does not exist */

        if (!$post) {

            $_SESSION['flash_message'] = 'Blog post not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Return edit form view with blog post data */

        return [

            'title' => 'Edit Blog Post',

            'template' => 'editBlog.html.php',

            'styles' => ['admin-form.css'],

            'variables' => [

                'post' => $post

            ]

        ];

    }



    /* Handle submission of edited blog post data */

    public function updateBlog(): array

    {

        /* Ensure only admins can update blog posts */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Get blog post ID from POST data */

        $postid = (int) ($_POST['postid'] ?? 0);



        /* Redirect if the blog post ID is missing */

        if (!$postid) {

            $_SESSION['flash_message'] = 'Blog post not found.';

            $_SESSION['flash_type'] = 'info';



            return ['redirect' => '/admin'];

        }



        /* Save updated blog post details using the model */

        $this->blogPosts->save([

            'postid' => $postid,

            'title' => trim($_POST['title'] ?? ''),

            'category' => trim($_POST['category'] ?? ''),

            'content' => trim($_POST['content'] ?? '')

        ]);



        /* Store success feedback after update */

        $_SESSION['flash_message'] = 'Blog post updated successfully.';

        $_SESSION['flash_type'] = 'success';



        /* Redirect back to the admin dashboard */

        return ['redirect' => '/admin'];

    }



    /* Delete a blog post selected by the administrator */

    public function deleteBlog(): array

    {

        /* Ensure only admins can delete blog posts */

        if ($result = $this->requireAdmin()) {

            return $result;

        }



        /* Get blog post ID from URL */

        $id = $_GET['id'] ?? null;



        /* Remove blog post from the database if an ID was provided */

        if ($id) {

            $this->blogPosts->delete((int) $id);

        }



        /* Store success feedback after deletion */

        $_SESSION['flash_message'] = 'Blog post deleted successfully.';

        $_SESSION['flash_type'] = 'success';



        /* Redirect back to the admin dashboard */

        return ['redirect' => '/admin'];

    }

}