var gulp = require('gulp')
  , fstream = require('fstream')
  , tar = require('tar')
  , zlib = require('zlib')
  , crypto = require('crypto')
  , fs = require('fs')
  , path = require('path')
  , moment = require('moment')
  , util = require('util')
  , xml = require('xmlbuilder')
  , _ = require('lodash')

var multiline = function (fn) {
  return fn.toString().replace(/(^function\s*\(\)\s+{\s+\/\*)|(\s*\*\/\;*\s*})/g, '')
}

gulp.task('generate:package', function() {
  var buildTree = function (dir, tree) {
    var base = fs.readdirSync(dir)

    base.forEach(function (file) {
      var full = path.join(dir, file)
      if (!tree['#list']) { tree['#list'] = [] }

      if (fs.statSync(full).isDirectory()) {
        var child = { 'dir': { '@name': file } }
        tree['#list'].push(child)
        buildTree(full, child['dir'])
      }
      else {
        var md5 = crypto.createHash('md5')
        var content = fs.readFileSync(full, { encoding: 'utf8' })
        md5.update(content)
      
        var child = { 'file': { '@name': file, '@hash': md5.digest('hex') } }
        tree['#list'].push(child)
      }
    })

    return tree
  }

  var meta = {
    'name': 'gimmie',
    'version': '1.0.27',
    'stability': 'stable',
    'license': 'MITL',
    'channel': 'community',
    'extends': {},
    'summary': 'Adding Gimmie rewards to Magento and allow user earn points and get real rewards when purchasing and referral friends.',
    'description': {
      '#cdata': multiline(function() {
/*
# Gimmie Magento plugin

Adding Gimmie rewards to Magento and allow user earn points and get real rewards when purchasing and referral friends.

# Features

- Set reward country for filter rewards available in country or auto detect from user IP address.
- Show/Hide Gimmie view when embed in site.
- Enable/Disable events that you want to give out points in settings.
- Give out points base on amount that user purchase when user commit transaction.

# How to insert gimmie view to Magento

On theme design, add link or button to menu with `gm-view="catalog"` or class name `.gm-open-catalog`.
 */
      })
    },
    'notes': {
      '#cdata': multiline(function() {
/*
Other multiline here
v 1.0.27

- Fix top spender of the month doesn't trigger.

v 1.0.26

- Add loggly for gathering information from client site.

v 1.0.25

- Fix package channel.

v 1.0.24

- Add user login event.
- Add buy item from special catalog event.
- Add subscribe to newsletter event.
- Change some typo and bug fixes.

v 1.0.23

- Drop event prefix.

v 1.0.22

- Add option to add Gimmie popup link on top menu.

v 1.0.21

- Changed all events name in full version.

v 1.0.20 

- Changed referral message in dashboard.

v 1.0.19

- Fixed error in register success event.

v 1.0.18

- Added register success event.

v 1.0.17

- Changed event name for free plugin.

v 1.0.16

- Changed scheduler time to first day of the month on midnight.

v 1.0.15

- Fixed secure site cannot load user profile from proxy.

v 1.0.14

- Added terms and conditions field.

v 1.0.13

- Fixed register when checkout doesn't trigger referral.

v 1.0.12

- Fixed referral doesn't trigger events.
- Fixed place the order redirect back to order lists page.

v 1.0.11

- Moved style block to below script.

v 1.0.10

- Added hide "Sponsor here" from catalog option.

v 1.0.9

- Fixed referral doesn't earn points.

v 1.0.8

- Changed exchange points state from transaction complete to shipment.
- Added social and share link back.

v 1.0.7

- Removed exchanges rate.

v 1.0.6

- Changed events name.

v 1.0.5

- Fixed session error cause cronjob cannot run.

v 1.0.4

- Floor down the amount/prices.
- Fixed anonymous user should not have data in Gimmie Widget options.

v 1.0.3

- Hide notice message and changed the way check id.

v 1.0.2

- Fixed confirm order and got blank page.
- Fixed some event doesn't trigger.

v 1.0.1

- Fixed cannot open Gimmie Widget.

Initial Release 1.0.0

- Set reward country for filter rewards available in country or auto detect from user IP address.
- Show/Hide Gimmie view when embed in site.
- Enable/Disable events that you want to give out points in settings.
- Give out points base on amount that user purchase when user commit transaction.


 */
      })
    },
    'authors': [
      { 'author': {
          'name': 'Maythee Anegboonlap',
          'user': 'maythee',
          'email': 'maythee@gimmie.io'
        } 
      }
    ],
    'date': moment().format('YYYY-MM-DD'),
    'time': moment().format('hh:mm:ss'),
    'contents': [
      { target: buildTree(path.join(__dirname, 'app', 'code', 'community'), { '@name': 'magecommunity' }) },
      { target: buildTree(path.join(__dirname, 'app', 'design'), { '@name': 'magedesign' }) },
      { target: buildTree(path.join(__dirname, 'app', 'etc'), { '@name': 'mageetc' }) },
      { target: buildTree(path.join(__dirname, 'lib'), { '@name': 'magelib' }) },
      { target: buildTree(path.join(__dirname, 'skin'), { '@name': 'mageskin' }) }
    ],
    'compatible': {},
    'dependencies': {
      'required': {
        'php': { 'min': '5.3.0', 'max': '6.0.0' }
      }
    }
  }
  fs.writeFileSync('package.xml', xml.create('package').ele(meta).end({ pretty: true }))
})

gulp.task('archive', [ 'generate:package' ], function () {

  var packageFile = 'gimmie.tgz'
  var ignores = _([ 'node_modules', '.DS_Store', '.gitmodules', '.git', '.gitignore', 'package.json', 'gulpfile.js', packageFile ])
    .inject(function(r, v) {
      r[v] = true
      return r
    }, {})

  fstream.Reader({ 
    path: __dirname, 
    root: './',
    filter: function(fstream, file) {
      return !ignores[file.basename]
    }
  }).pipe(tar.Pack())
    .pipe(zlib.Gzip())
    .pipe(fstream.Writer({ 'path': packageFile }))

})

gulp.task('default', [ 'archive' ])
