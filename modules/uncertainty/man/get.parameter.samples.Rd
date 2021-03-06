% Generated by roxygen2: do not edit by hand
% Please edit documentation in R/get.samples.R
\name{get.parameter.samples}
\alias{get.parameter.samples}
\title{Sample from priors or posteriors}
\usage{
get.parameter.samples(pfts = settings$pfts, posterior.files = rep(NA,
  length(settings$pfts)), ens.sample.method = "uniform")
}
\arguments{
\item{pfts}{the pfts node of the list of pecan settings}
}
\description{
Convert priors / MCMC samples to chains that can be sampled for model parameters
}
\author{
David LeBauer, Shawn Serbin
}

