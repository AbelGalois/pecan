##' @name metric.residual.plot
##' @title metric.residual.plot
##' @export
##' @param dat
##' 
##' @author Betsy Cowdery

metric.residual.plot <- function(dat, var){
  
  require(ggplot2)
  
  ind <- intersect(which(!is.na(dat$obvs)),  which(!is.na(dat$model)))
  
  dat <- dat[ind,]
  dat$time <- lubridate::year(as.Date(as.character(dat$time), format="%Y",))
  dat$diff <- dat$model - dat$obvs
  
  ggplot(data = dat) + 
    geom_path(aes(x=time,y=rep(0, length(time))), colour = "#666666", size=2, linetype = 2, lineend = "round") +
    geom_point(aes(x=time,y=diff), size=4,  colour = "#619CFF") + labs(title=var, x= "years", y="model - observation")
  
  # ind <- intersect(which(!is.na(dat$obvs)),  which(!is.na(dat$model)))
  # plot(dat$model[ind]-dat$obvs[ind], ylim = c(-max(dat$model[ind]-dat$obvs[ind]),max(dat$model[ind]-dat$obvs[ind])))
  # abline(h=0)
  
  return(NA)
}

