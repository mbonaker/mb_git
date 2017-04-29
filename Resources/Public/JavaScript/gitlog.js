TYPO3.jQuery('document').ready(function() {
	var gitGraphTemplate = new GitGraph.Template({
		colors: ['#FF8700', '#8C8C8C', '#000000'],
		branch: {
			lineWidth: 5,
			spacingX: 29,
			spacingY: 29,
			showLabel: false
		},
		commit: {
			spacingX: -29,
			spacingY: 0,
			dot: {
				size: 9
			},
			shouldDisplayTooltipsInCompactMode: true
		}
	});
	var gitgraph = new GitGraph({
		'elementId': 'gitGraph',
		'mode': 'compact',
		'template': gitGraphTemplate,
		'initCommitOffsetY': 0
	});
	function getCommitChildren(sha1) {
		var i, commit;
		var children = [];
		for(i in data.commits) if(data.commits.hasOwnProperty(i)) {
			commit = data.commits[i];
			if(commit.parentHashes.indexOf(sha1) !== -1) {
				children.push(commit);
			}
		}
		return children;
	}
	function getCommitParents(commit) {
		var i, parent;
		var parents = [];
		for(i in data.commits) if(data.commits.hasOwnProperty(i)) {
			parent = data.commits[i];
			if(commit.parentHashes.indexOf(parent.hash) !== -1) {
				parents.push(parent);
			}
		}
		return parents;
	}
	var data = TYPO3.jQuery(document.getElementById('gitGraph')).data('log');
	var commits = {}, branches = {}, tags = {};
	var i, j, commit;
	for(i in data.branches) if(data.branches.hasOwnProperty(i)) {
		branches[data.branches[i].name] = data.branches[i];
	}
	for(i in data.commits) if(data.commits.hasOwnProperty(i)) {
		commit = data.commits[i];
		commits[commit.hash] = commit;
		commit.parents = getCommitParents(commit);
		commit.children = getCommitChildren(commit.hash);
		if(commit.children.length > 0) {
			commit.children[0].createBranch = false;
		}
		for(j = 1; j < commit.children.length; j++) {
			commit.children[j].createBranch = true;
		}
	}
	for(i in data.tags) if(data.tags.hasOwnProperty(i)) {
		commits[data.tags[i].name] = data.tags[i];
	}
	var gitActions = [];
	for(i in data.commits) if(data.commits.hasOwnProperty(i)) {
		commit = data.commits[i];
		gitActions.push(new GitAction(commit.author.date, function () {
			if(commit.createBranch) {
			}
			this.getMainBranch().commit({
				sha1: commit.hash,
				message: commit.message + commit.bodyMessage,
				author: commit.author.name + "<" + commit.author.email + ">",
				date: commit.author.date
			});
		}));
	}

	var commitsByHash = {};
	var baseCommits = [];
	var commit, c, baseCommit;
	function drawCommit(commit, baseRevision) {
		return baseRevision.commit({
			sha1: commit.hash,
			message: commit.message + commit.bodyMessage,
			author: commit.author.name + "<" + commit.author.email + ">",
			date: commit.author.date
		});
	}

	function drawCommitDescendants(baseCommit, graph) {
		var c, commit;
		var knownChildren = [];
		var cBranch = graph;
		for(c in data.commits) if(data.commits.hasOwnProperty(c)) {
			commit = data.commits[c];
			if(commit.parentHashes.indexOf(baseCommit.hash) !== -1) {
				if(c > 0) {
					cBranch = graph.branch("new-branch" + Math.random())
				} else {
					cBranch = graph;
				}
				knownChildren.push({commit: commit, branch: cBranch});
			}
		}
		for(c in knownChildren) if(knownChildren.hasOwnProperty(c)) {
			commit = knownChildren[c].commit;
			cBranch = knownChildren[c].branch;
			cBranch.checkout();
			drawCommitDescendants(commit, drawCommit(commit, cBranch));
		}
	}
	for(c in data.commits) if(data.commits.hasOwnProperty(c)) {

		commit = data.commits[c];
		commitsByHash[commit.hash] = commit;
		if(commit.parentHashes.length == 0) {
			gitgraph.branch("new-branch" + Math.random());
			drawCommitDescendants(commit, drawCommit(commit, gitgraph));
		}
	}
});
