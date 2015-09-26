# Embedly Wordpress Plugin

Updating the plugin:

1. Ensure the github repo readme.txt and embedly.php are showing the correct version of the plugin (i.e. from 4.0.1 -> 4.0.2, this version needs to be reflected in these two files)

2 in the svn repo, `svn up` to update, if necessary

3. `cp github_repo_dir/* svn_repo_dir/trunk/` (remove any .git or other unnecessary files)

4. `cd svn_repo_dir/trunk`

5. check updates with `svn stat` and `svn diff` (especially the version bump)
 
6. `svn ci -m "a public message" --username USERNAME --password PASSWORD`

7. `cd ..` go up to the master svn directory

8. make a new tag `mkdir tags/X.Y.Z && svn add tags/X.Y.Z`

9. svn-copy the trunk files `svn cp /trunk/* tags/X.Y.Z/`

10. Check changes again with `svn stat` and `svn diff` if necessary

11. `svn ci -m "tagging version X.Y.Z" --username USERNAME --author AUTHOR`
