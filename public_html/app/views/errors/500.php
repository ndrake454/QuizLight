<!-- app/views/errors/500.php -->
<div class="flex flex-col items-center justify-center min-h-[60vh]">
    <div class="text-6xl font-bold text-indigo-300 mb-4">500</div>
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Server Error</h1>
    <p class="text-gray-600 mb-8">Something went wrong on our end. Please try again later.</p>
    <?php if (isset($error) && DISPLAY_ERRORS): ?>
        <div class="bg-red-100 text-red-700 p-4 mb-6 rounded-md max-w-md w-full">
            <p class="font-bold">Error details:</p>
            <p class="text-sm"><?php echo $error; ?></p>
        </div>
    <?php endif; ?>
    <a href="/" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition duration-150">
        Return to Home
    </a>
</div>