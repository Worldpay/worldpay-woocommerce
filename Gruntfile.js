module.exports = function (grunt) {
	grunt.loadNpmTasks('grunt-replace');
	grunt.loadNpmTasks('grunt-zip');
	grunt.loadNpmTasks('grunt-contrib-copy');
	var path = require('path');
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		version: grunt.option('libv'),
		copy: {
		  main: {
		    files: [
		      {expand: true, src: ['Constants/**'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['Forms/**'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['languages/**'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['libs/**'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['Persistence/**'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['scripts/**'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['Webhooks/**'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['README.md'], dest: 'target/worldpay-woocommerce/'},
		      {expand: true, src: ['woocommerce-gateway-worldpay.php'], dest: 'target/worldpay-woocommerce/'},
		    ]
		  }
		},
		replace: {
	      prod: {
	        options: {
	          patterns: [
	             {
	              match: 'PLUGINVERSION',
	              replacement: '<%= version %>'
	            },
	            {
	              match: /'api_endpoint[\s\S]*?'default[\s\S]*?\),/gm,
	              replacement: ''
	            },
	            {
	              match: /'js_endpoint[\s\S]*?'default[\s\S]*?\)/gm,
	              replacement: ''
	            },
	          ]
	        },
	        files: [
	           {expand: true, flatten: true, src: ['README.md'], dest: 'target/worldpay-woocommerce/'},
	           {expand: true, flatten: true, src: ['woocommerce-gateway-worldpay.php'], dest: 'target/worldpay-woocommerce/'},
	           {expand: true, flatten: true, src: ['Forms/admin-form.php'], dest: 'target/worldpay-woocommerce/Forms/'},
	        ]
	      },
	    },
	    zip: {
	    	prod: {
	    		router: function (filepath) {
	    			if (filepath.indexOf('README.md') != -1) {
	    				return 'worldpay-woocommerce/WORLDPAY-README.md';
	    			}
				    return filepath.replace('target/', '');
				},
	    		src: ['target/**'],
	    		dest: 'release/worldpay-woocommerce.zip',
	    		dot: false
	    	}
	    	
	    }
	});

	grunt.registerTask('package', [
		'copy',
		'replace',
		'zip'
	]);

	grunt.registerTask('ci', [
		'package',
		'test'
	]);

};