#' @name invert.auto 
#' 
#' @title Inversion with automatic convergence checking
#' @details Performs an inversion via the `invert.custom` function with multiple chains and automatic convergence checking. Convergence checks are performed using the multivariate Gelman-Rubin diagnostic.
#' @param observed Matrix of observed values. Must line up with output of 'model'.
#' @param invert.options R list object containing the following elements:
#' 
#' inits Vector of initial values of model parameters to be inverted.
#'
#' ngibbs Number of MCMC iterations
#'
#' prior.function Function for use as prior. Should take a vector of parameters 
#' as input and return a single value -- the sum of their log-densities -- as 
#' output.
#'
#' param.mins Vector of minimum values for inversion parameters
#'
#' model The model to be inverted. This should be an R function that takes 
#' `params` as input and returns one column of `observed` (nrows should be the 
#' same). Constants should be implicitly included here.
#'
#' adapt Number of steps for adapting covariance matrix (i.e. adapt every 'n' 
#' steps). Default=100
#' adj_min Minimum threshold for rescaling Jump standard deviation.  Default = 
#' 0.1.
#' 
#' target Target acceptance rate. Default=0.234, based on recommendation for 
#' multivariate block sampling in Haario et al. 2001
#' 
#' do.lsq Perform least squares optimization first (see `invert.lsq`), and use 
#' outputs to initialize Metropolis Hastings. This may improve mixing time, but 
#' risks getting caught in a local minimum.  Default=FALSE
#'
#' nchains Number of independent chains.
#' 
#' inits.function Function for randomly generating initial conditions.
#' burnin Number of samples to burn-in before computing Gelman 
#' Diagnostic. Default = 0.8 * ngibbs.
#'
#' n.tries Number of attempted runs before giving up. Default = 5
#'
#' do.lsq.first Initialize using least-squares optimization on first 
#' try. Default = FALSE.
#'
#' do.lsq.after Number of tries before starting initialization using 
#' least-squares optimization. Default = TRUE.
#'
#' target.adj Amount by which to adjust target acceptance rate every 
#' attempt. Default = 0.8
#'
#' @param return.samples Include full samples list in output. Default = TRUE.
#' @param save.samples Filename for saving samples after each iteration. If 
#' 'NULL', do not save samples. Default = NULL.
#' @param parallel Logical. Whether or not to run multiple chains in parallel on multiple cores (defualt=FALSE).
#' @param parallel.cores Number of cores to use for parallelization. If NULL (default), allocate one fewer than detected number of cores.
#' @param ... Other arguments to `check.convergence`
#' @return List of "results" (summary statistics and Gelman Diagnostic) and 
#' "samples"(mcmc.list object, or "NA" if return.samples=FALSE)

invert.auto <- function(observed, invert.options, return.samples=TRUE, save.samples=NULL, quiet=FALSE, parallel=FALSE, parallel.cores=NULL, ...){
    library(coda)
    n.tries <- invert.options$n.tries
    nchains <- invert.options$nchains
    inits.function <- invert.options$inits.function
    invert.options$do.lsq <- invert.options$do.lsq.first
    if(invert.options$do.lsq) library(minpack.lm)
    try.again <- TRUE
    i.try <- 1
    while(try.again & i.try <= n.tries){
        print(sprintf("Attempt %d of %d", i.try, n.tries))
        if(parallel & !require(parallel)){
            warning("'parallel' package not installed. Proceeding without parallelization")
            parallel <- FALSE
        }
        if(parallel){
            library(parallel)
            invert.function <- function(x){
                set.seed(x)
                invert.options$inits <- inits.function()
                samps <- invert.custom(observed=observed, invert.options=invert.options, quiet=quiet)
                return(samps)
            }
            maxcores <- detectCores()
            if(is.null(parallel.cores)){
                cl <- makeCluster(maxcores - 1, "FORK")
            } else {
                if(!is.numeric(parallel.cores) | parallel.cores %% 1 != 0){
                    stop("Invalid argument to 'parallel.cores'. Must be integer or NULL")
                } else if (parallel.cores > maxcores){
                    warning(sprintf("Requested %1$d cores but only %2$d cores available. Using only %2$d cores.", parallel.cores, maxcores))
                    parallel.cores <- maxcores
                }
                cl <- makeCluster(parallel.cores, "FORK")
            }
            print(sprintf("Running %d chains in parallel. Progress bar unavailable", nchains))
            seed.list <- as.list(1e8 * runif(nchains))
            samps.list <- parLapply(cl, seed.list, invert.function)
        } else {
            message("Running in serial mode. Better performance can be achieved by running multiple chains in parallel (set 'parallel=TRUE').")
            samps.list <- list()
            for(chain in 1:nchains){
                print(sprintf("Chain %d of %d", chain, nchains))
                invert.options$inits <- inits.function() 
                samps.list[[chain]] <- invert.custom(observed=observed, invert.options=invert.options, quiet=quiet)
            }
        }
        if(!is.null(save.samples)) save(samps.list, file=save.samples)
        # Check for convergence. Repeat if necessary.
        samps.list.bt <- lapply(samps.list, burnin.thin, burnin=burnin, thin=1)
        smcmc <- as.mcmc.list(lapply(samps.list.bt, as.mcmc))
        conv.check <- check.convergence(smcmc, ...)
        if(conv.check$error) {
            i.try <- i.try + 1
            warning("Could not calculate Gelman diag. Trying again")
            next
        } else {
            if(conv.check$converged){
                try.again <- FALSE
                samps <- burnin.thin(do.call(rbind, samps.list.bt), burnin=0)
                results <- summary.simple(samps)
                results$gelman.diag <- conv.check$diagnostic
            } else {
                i.try <- i.try + 1
                invert.options$target <- invert.options$target * invert.options$target.adj
                if(i.try > invert.options$do.lsq.after) invert.options$do.lsq <- TRUE
            }
        }
    }
    if(return.samples) samples <- as.mcmc.list(lapply(samps.list, as.mcmc))
    else samples <- NA
    if((i.try >= n.tries) & try.again){
        warning("Convergence was not achieved. Returning results as 'NA'.")
        results <- NA
    }
    out <- list(results=results, samples=samples)
    return(out)
}
