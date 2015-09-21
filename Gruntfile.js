
module.exports = function(grunt) {

  grunt.loadNpmTasks('grunt-contrib-jshint');

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    jshint: {
      options: {
        jshintrc: '.jshintrc'
      },

      all: ['Gruntfile.js', 'js/**/*.js']
    }
  });

  grunt.registerTask("default", ['jshint']);
};
